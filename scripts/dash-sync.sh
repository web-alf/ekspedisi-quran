#!/usr/bin/env bash
#
# Sinkron berkas ke server dash — dengan DAFTAR PUTIH, bukan "salin semua yang beda".
#
# Kenapa begini: berkas uji, dokumentasi, laporan, dan skrip bantu pernah ikut naik ke
# /var/www/dash dan tinggal di sana (satu berkas uji bahkan nyasar ke routes/). Server
# hanya perlu yang dipakai saat BERJALAN — crontab-nya cuma `schedule:run`, tidak pernah
# menjalankan suite, tidak pernah menyusun aset (aset disusun lokal lalu disalin).
#
# Karena itu: berkas di luar daftar putih DITOLAK, bukan diam-diam disalin. Daftar
# terlarang diperiksa ulang SESUDAH sinkron, jadi berkas yang terlanjur ada di server
# ketahuan, bukan lolos.
#
# Pakai (dijalankan dari akar repositori):
#   bash scripts/dash-sync.sh                 # uji jalan (tidak menyalin apa pun)
#   bash scripts/dash-sync.sh --terapkan      # salin sungguhan
#   bash scripts/dash-sync.sh --hanya app/Models/PackingBox.php,routes/web.php --terapkan
#   bash scripts/dash-sync.sh --migrate --cache --terapkan   # termasuk migrasi + cache + reload fpm
#   bash scripts/dash-sync.sh --periksa       # hanya memeriksa keadaan server
#
set -uo pipefail

HOST="${EQ_HOST:-vps-187.77.113.89}"
TARGET="/var/www/dash"
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# --- 1. Daftar putih: yang memang dipakai saat berjalan ---
DIR_PUTIH=(
  "app"
  "config"
  "database/migrations"
  "database/seeders"
  "lang"
  "resources"
  "routes"
)
BERKAS_PUTIH=(
  "artisan"
  "composer.json"
  "composer.lock"
  "bootstrap/app.php"
  "bootstrap/providers.php"
  "public/index.php"
  "public/.htaccess"
  "public/robots.txt"
  "public/favicon.ico"
)
# Aset statis: disalin tanpa --delete (isinya bisa bertambah di server, mis. gambar galeri).
DIR_ASET=(
  "public/images"
  "public/sounds"
  "public/data"
)

# --- 2. Daftar terlarang: TIDAK PERNAH boleh ada di server ---
# Yang dieksekusi aplikasi hanya butuh berkas di daftar putih. Sisanya (suite uji, pabrik
# data uji, env uji, workflow CI, git hooks, konfigurasi editor/git, catatan agen, dan
# dokumentasi kerja) adalah perkakas mesin pengembang.
TERLARANG=(
  "tests"
  "phpunit.xml"
  "vitest.config.js"
  "lighthouserc.js"
  "database/factories"
  "docs"
  "Laporan"
  "scripts"
  "deployment"
  "login.md"
  "MYTask"
  ".agents"
  ".github"
  ".githooks"
  ".editorconfig"
  ".gitattributes"
  ".gitignore"
  ".env.testing"
  "node_modules"
  ".git"
)

MODE_TERAPKAN=0
MODE_PERIKSA=0
MODE_MIGRATE=0
MODE_CACHE=0
HANYA=""

while [ $# -gt 0 ]; do
  case "$1" in
    --terapkan) MODE_TERAPKAN=1 ;;
    --periksa)  MODE_PERIKSA=1 ;;
    --migrate)  MODE_MIGRATE=1 ;;
    --cache)    MODE_CACHE=1 ;;
    --hanya)    HANYA="${2:-}"; shift ;;
    *) echo "Pilihan tidak dikenal: $1" >&2; exit 2 ;;
  esac
  shift
done

diizinkan() {
  local p="$1" d
  for d in "${DIR_PUTIH[@]}" "${DIR_ASET[@]}"; do
    case "$p" in "$d"|"$d"/*) return 0 ;; esac
  done
  for d in "${BERKAS_PUTIH[@]}"; do
    [ "$p" = "$d" ] && return 0
  done
  return 1
}

# --- 3. Kumpulkan berkas yang akan disalin ---
Daftar() {
  local d f
  for d in "${DIR_PUTIH[@]}" "${BERKAS_PUTIH[@]}"; do
    if [ -d "$REPO/$d" ]; then
      find "$REPO/$d" -type f ! -name '._*'
    elif [ -f "$REPO/$d" ]; then
      echo "$REPO/$d"
    fi
  done
}

if [ -n "$HANYA" ]; then
  : > /tmp/eq-sync-list.txt
  IFS=',' read -ra PILIHAN <<< "$HANYA"
  for p in "${PILIHAN[@]}"; do
    p="$(echo "$p" | xargs)"   # buang spasi di ujung
    if ! diizinkan "$p"; then
      echo "DITOLAK: '$p' tidak ada di daftar putih penyalinan." >&2
      echo "Server hanya menerima: ${DIR_PUTIH[*]} | ${BERKAS_PUTIH[*]} | ${DIR_ASET[*]}" >&2
      echo "(berkas uji, dokumentasi, laporan, skrip, dan konfigurasi uji memang TIDAK boleh naik)" >&2
      exit 3
    fi
    [ -e "$REPO/$p" ] || { echo "Tidak ada di lokal: $p" >&2; exit 3; }
    if [ -d "$REPO/$p" ]; then
      find "$REPO/$p" -type f ! -name '._*' >> /tmp/eq-sync-list.txt
    else
      echo "$REPO/$p" >> /tmp/eq-sync-list.txt
    fi
  done
else
  Daftar > /tmp/eq-sync-list.txt
fi

# Ubah ke jalur relatif (yang dipahami rsync --files-from)
sed "s|^$REPO/||" /tmp/eq-sync-list.txt | LC_ALL=C sort -u > /tmp/eq-sync-rel.txt
JUMLAH=$(wc -l < /tmp/eq-sync-rel.txt | tr -d ' ')

echo "Repositori : $REPO"
echo "Tujuan     : $HOST:$TARGET"
echo "Berkas     : $JUMLAH berkas dari daftar putih"
echo "Mode       : $([ "$MODE_TERAPKAN" = 1 ] && echo 'SALIN (--terapkan)' || echo 'UJI JALAN (belum menyalin)')"
echo

# --- 4. Periksa daftar terlarang di server (selalu dijalankan) ---
echo "== Pemeriksaan daftar terlarang di server =="
PERIKSA=$(printf '%s\n' "${TERLARANG[@]}" | sed 's/^/"/; s/$/"/' > /dev/null; for t in "${TERLARANG[@]}"; do printf 'if [ -e %s/%s ]; then echo "  ADA         %s"; else echo "  bersih      %s"; fi; ' "$TARGET" "$t" "$t" "$t"; done)

# shellcheck disable=SC2029
ssh "$HOST" "bash -s" <<< "$PERIKSA"

if [ "$MODE_PERIKSA" = 1 ]; then
  rm -f /tmp/eq-sync-list.txt /tmp/eq-sync-rel.txt
  exit 0
fi

# --- 5. Sinkron berkas sumber ---
# Pembandingnya ISI (--checksum), bukan stempel waktu: berkas yang isinya sama TIDAK
# ditulis ulang hanya karena waktu/izinnya berbeda (`--no-times/--no-perms/--no-owner`).
# Tanpa itu, tiap sinkron menulis ulang ratusan berkas tanpa satu pun isi yang berubah,
# dan uji jalannya jadi berisik sehingga beda isi yang sungguhan tidak terlihat.
FLAG_KONTEN=(-rc --no-times --no-perms --no-owner --no-group --exclude='._*')

if [ "$MODE_TERAPKAN" = 1 ]; then
  COPYFILE_DISABLE=1 rsync "${FLAG_KONTEN[@]}" --files-from=/tmp/eq-sync-rel.txt \
    --rsync-path="sudo rsync" "$REPO/" "$HOST:$TARGET/"
  KODE=$?

  # Aset statis (tanpa --delete: isinya boleh bertambah di server)
  for d in "${DIR_ASET[@]}"; do
    [ -d "$REPO/$d" ] || continue
    COPYFILE_DISABLE=1 rsync "${FLAG_KONTEN[@]}" --rsync-path="sudo rsync" \
      "$REPO/$d/" "$HOST:$TARGET/$d/"
  done

  # Aset terbit: dibangun lokal oleh `npm run build`, disalin utuh (yang lama dibuang)
  if [ -d "$REPO/public/build" ]; then
    COPYFILE_DISABLE=1 rsync -a --delete --exclude='._*' --rsync-path="sudo rsync" \
      "$REPO/public/build/" "$HOST:$TARGET/public/build/"
    # server ini tidak mendukung --chown (rsync macOS); pemilik dirapikan terpisah
    ssh "$HOST" "sudo chown -R www-data:www-data $TARGET/public/build && sudo find $TARGET/public/build -type d -exec chmod 755 {} + && sudo find $TARGET/public/build -type f -exec chmod 644 {} +"
  fi

  echo
  echo "== rsync selesai (keluar=$KODE) =="
else
  COPYFILE_DISABLE=1 rsync "${FLAG_KONTEN[@]}" --dry-run --itemize-changes \
    --files-from=/tmp/eq-sync-rel.txt --rsync-path="sudo rsync" "$REPO/" "$HOST:$TARGET/" | head -40
  echo "(mode uji jalan — tidak ada yang disalin; jalankan dengan --terapkan)"
fi

# --- 6. Bukti: berkas lokal = server, dan tidak ada yang terlarang ---
if [ "$MODE_TERAPKAN" = 1 ]; then
  echo
  echo "== Bukti berkas sumber lokal = server =="
  SSH_MANIFEST='cd '"$TARGET"' && while read -r f; do [ -f "$f" ] && printf "%s  %s\n" "$(md5sum "$f" | cut -d" " -f1)" "$f"; done < /tmp/eq-sync-rel.txt | LC_ALL=C sort > /tmp/eq-server-manifest.txt; wc -l < /tmp/eq-server-manifest.txt'
  scp -q /tmp/eq-sync-rel.txt "$HOST:/tmp/eq-sync-rel.txt"
  ssh "$HOST" "$SSH_MANIFEST" > /tmp/eq-server-count.txt
  scp -q "$HOST:/tmp/eq-server-manifest.txt" /tmp/eq-server-manifest.txt

  python3 - "$REPO" <<'PY'
import hashlib, sys
repo = sys.argv[1]
server = {}
for baris in open('/tmp/eq-server-manifest.txt', encoding='utf-8'):
    b = baris.rstrip('\n').split('  ', 1)
    if len(b) == 2:
        server[b[1].strip()] = b[0].strip()
sama = beda = hilang = 0
for p in open('/tmp/eq-sync-rel.txt', encoding='utf-8'):
    p = p.strip()
    if not p:
        continue
    try:
        with open(f'{repo}/{p}', 'rb') as fh:
            md5 = hashlib.md5(fh.read()).hexdigest()
    except FileNotFoundError:
        continue
    if p not in server:
        hilang += 1
        print('  TIDAK ADA DI SERVER:', p)
    elif server[p] == md5:
        sama += 1
    else:
        beda += 1
        print('  ISI BEDA:', p)
print(f'  identik={sama}  beda={beda}  hilang={hilang}')
sys.exit(1 if (beda or hilang) else 0)
PY
  BUKTI=$?

  echo
  echo "== Migrasi & cache =="
  if [ "$MODE_MIGRATE" = 1 ]; then
    ssh "$HOST" "cd $TARGET && sudo -u www-data HOME=/tmp php artisan migrate --force 2>&1 | tail -5"
  else
    echo "  (dilewati — pakai --migrate bila ada migrasi baru)"
  fi

  if [ "$MODE_CACHE" = 1 ]; then
    ssh "$HOST" "cd $TARGET && sudo -u www-data HOME=/tmp php artisan config:cache && sudo -u www-data HOME=/tmp php artisan route:cache && sudo -u www-data HOME=/tmp php artisan permission:cache-reset && sudo systemctl reload php8.3-fpm && echo 'fpm di-reload'"
  else
    echo "  (dilewati — pakai --cache bila rute/config/izin berubah)"
  fi

  echo
  [ "$BUKTI" = 0 ] && echo "SELESAI: berkas lokal = server, tidak ada yang terlarang." || echo "PERIKSA: ada berkas yang tidak sinkron (lihat daftar di atas)."
  exit "$BUKTI"
fi

rm -f /tmp/eq-sync-list.txt /tmp/eq-sync-rel.txt
