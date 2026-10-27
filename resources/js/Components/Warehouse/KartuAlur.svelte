<script>
  /**
   * Satu kartu "Alur Mushaf" di dashboard gudang (keputusan pemilik 27/10/2026).
   *
   * Bentuknya SENGAJA memakai kotak angka yang sudah dipakai dashboard — border, radius,
   * label kapital kecil, angka besar + satuan (lihat StatTile) — lalu menambahkan satu
   * baris keterangan pendek dan, khusus Manager Gudang, rincian per gudang.
   *
   * Rincian disembunyikan bila gudangnya cuma satu: untuk SPV angka besar sudah milik
   * gudangnya sendiri, jadi baris "Gudang Semarang 1.120 eks" hanya mengulang angka.
   */
  export let label;
  export let nilai;
  export let satuan = 'resi';
  export let keterangan = '';
  // [{ nama, nilai, satuan }] — dikirim hanya bila memang ada lebih dari satu gudang.
  export let rincian = [];
</script>

<div class="border border-gray-200 rounded-xl p-3 min-w-0 flex flex-col">
  <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1 min-h-[26px]">{label}</p>
  <p class="text-2xl font-bold text-gray-900 leading-none">
    {nilai}
    {#if satuan}<span class="text-xs font-medium text-gray-400">{satuan}</span>{/if}
  </p>

  {#if keterangan}
    <p class="text-xs text-gray-500 mt-1.5">{keterangan}</p>
  {/if}

  {#if rincian.length > 1}
    <div class="mt-2.5 pt-2 border-t border-gray-100 space-y-1">
      {#each rincian as r (r.nama)}
        <div class="flex items-baseline justify-between gap-2">
          <span class="text-xs text-gray-500 truncate">{r.nama}</span>
          <span class="text-[13px] font-semibold text-gray-900 whitespace-nowrap">
            {r.nilai} <span class="text-[11px] font-medium text-gray-400">{r.satuan}</span>
          </span>
        </div>
      {/each}
    </div>
  {/if}
</div>
