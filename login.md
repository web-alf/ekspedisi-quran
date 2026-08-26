# Login Credentials

Proyek: **Ekspedisi Quran** (Laravel 12 + Svelte 4 + Inertia.js v2)
URL: **http://127.0.0.1:8000**

## Admin & Role Users

Seeder: `database/seeders/UserSeeder.php`. Untuk seed ulang: `php artisan db:seed --class=UserSeeder`.

| Role | Email | Password | Hak Akses |
|---|---|---|---|
| **Super Admin** | `admin@ekspedisiquran.com` | `password123` | Full access kecuali direct warehouse ops |
| Customer Service | `cs@ekspedisiquran.com` | `cs123` | Donatur CRUD, mushaf request approval, tracking |
| Warehouse | `gudang@ekspedisiquran.com` | `gudang123` | Packing ops, QR scan, box management, certificate generation |
| Supervisor | `supervisor@ekspedisiquran.com` | `supervisor123` | Task assignment, monitoring, performance reports |
| Courier | `kurir@ekspedisiquran.com` | `kurir123` | Shipment status updates, tracking |

## User Lainnya (dari dump original)

Data asli dari backup `db_ekspedisi_quran` — user tambahan selain 5 seeder di atas:

| Email | Nama | Role (via Spatie) |
|---|---|---|
| `ekspedisiquran@alfatihah.com` | Nalurita Firdausyah | super-admin |
| `ekspedisiquran@gmail.com` | Pipit Riandini | super-admin |
| `supervisor1@ekspedisiquran.com` | Alfi Husnia Fitri | supervisor |
| `supervisor2@ekspedisiquran.com` | Ria Refriatin Febria | supervisor |
| `freelancer1@ekspedisiquran.com` | Qanita Nadya Ulya | (cek di DB) |

> Catatan: User tambahan ini mungkin dibuat lewat proses lain (bukan dari UserSeeder). Password di-hash dengan Bcrypt, tidak bisa di-recover. Cara reset: buat user baru lewat tinker atau phpMyAdmin.

Cek semua user:
```bash
/Users/Shared/DBngin/mariadb/10.11.6_arm/bin/mariadb --socket=/tmp/mariadb_3306.sock -uroot db_ekspedisi_quran \
  -e "SELECT id, name, email, is_active FROM users ORDER BY id;"
```

## Login Flow

1. Buka **http://127.0.0.1:8000/login**
2. Isi email + password
3. Submit → redirect ke **`/admin/dashboard`** (untuk super-admin)
4. Logout via menu user (pojok kanan atas) → balik ke `/login`

## Reset Password

**Tinker (paling cepat):**
```bash
php artisan tinker --execute='
$u = \App\Models\User::where("email", "admin@ekspedisiquran.com")->first();
$u->password = \Illuminate\Support\Facades\Hash::make("new_password");
$u->save();
echo "Password updated\n";
'
```

**Atau reset semua role user:**
```bash
php artisan migrate:fresh --seed
```
⚠️ `migrate:fresh` akan **hapus semua data** (donatur, pengiriman, dll) lalu re-seed dari seeder. Backup dulu jika ada data penting.

## Test Login via Browser

```bash
# Cek server masih jalan
curl -s -o /dev/null -w "Server: %{http_code}\n" http://127.0.0.1:8000/login

# Jika perlu restart
pkill -f "artisan serve"; pkill -f "vite"
cd /Users/zuraidasafitri/Downloads/app/ekspedisiQ
nohup php artisan serve --host=127.0.0.1 --port=8000 > /tmp/serve.log 2>&1 & disown
nohup npm run dev > /tmp/vite.log 2>&1 & disown
```

## Tracking Test (tanpa login)

Publik bisa tracking tanpa login:

```
http://127.0.0.1:8000/track/EQ-2026-00001
```

Sample nomor resi valid dari dump: `EQ-2026-00001`, `EQ-2026-18690` (sample acak).
