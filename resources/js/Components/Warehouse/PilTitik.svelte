<script>
  /**
   * Pil bergaris tepi dengan titik warna — satu-satunya gaya pil di
   * ringkasan gudang (persen, staf belum mulai, terdepan hari ini).
   */
  export let warna = 'indigo';

  const WARNA = {
    indigo: { teks: 'text-indigo-600', titik: 'bg-indigo-500' },
    amber: { teks: 'text-amber-700', titik: 'bg-amber-500' },
    green: { teks: 'text-green-700', titik: 'bg-green-500' },
    gray: { teks: 'text-gray-500', titik: 'bg-gray-400' },
  };

  $: w = WARNA[warna] ?? WARNA.indigo;
</script>

<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border border-gray-200 bg-white max-w-full {w.teks} {$$props.class ?? ''}">
  <span class="w-1.5 h-1.5 rounded-full flex-none {w.titik}"></span>
  <span class="truncate"><slot /></span>
</span>
