{{-- Label 10 × 15 cm: semua teks isi label diperbesar dengan SATU skala yang sama sampai
     label penuh, supaya ruang sisa terpakai tanpa merusak urutan besar-kecil huruf
     (nomor resi > nama jenis > judul seksi > isi > doa). Tanpa JavaScript skala tetap 1.
     Skala dihitung seolah header masih 20 mm: 6 mm yang dihemat header 14 mm disisihkan
     untuk kotak Jenis Wakaf, jadi ukuran huruf sama seperti sebelum header dipendekkan. --}}
<script>
(function () {
    const SKALA_MAKS = 1.35;
    const CADANGAN_HEADER_MM = 6;
    const CADANGAN_HEADER_PX = CADANGAN_HEADER_MM * 96 / 25.4;

    // Diukur dengan offset (bukan getBoundingClientRect) agar tidak terpengaruh transform
    // skala pratinjau layar; offset dibulatkan ke piksel, jadi toleransinya 1px. Seksi
    // terakhir diukur pada tinggi alaminya (flex dimatikan sementara) dan harus berakhir
    // sebelum ruang cadangan header.
    function meluap(label, seksiTerakhir) {
        const batasBawah = label.clientHeight - parseFloat(getComputedStyle(label).paddingBottom);

        return label.scrollHeight > label.clientHeight + 1
            || seksiTerakhir.offsetTop + seksiTerakhir.offsetHeight > batasBawah - CADANGAN_HEADER_PX + 1;
    }

    function sesuaikanSkala(label) {
        const seksiTerakhir = label.querySelector('.label-content').lastElementChild;
        seksiTerakhir.style.flex = 'none';
        label.style.setProperty('--skala', '1');

        if (!meluap(label, seksiTerakhir)) {
            let bawah = 1;
            let atas = SKALA_MAKS;
            for (let i = 0; i < 10; i++) {
                const tengah = (bawah + atas) / 2;
                label.style.setProperty('--skala', String(tengah));
                if (meluap(label, seksiTerakhir)) {
                    atas = tengah;
                } else {
                    bawah = tengah;
                }
            }
            label.style.setProperty('--skala', bawah.toFixed(3));
        }

        seksiTerakhir.style.flex = '';
    }

    function sesuaikanSemua() {
        document.querySelectorAll('.thermal-label').forEach(sesuaikanSkala);
    }

    sesuaikanSemua();
    window.addEventListener('load', sesuaikanSemua);
    window.addEventListener('beforeprint', sesuaikanSemua);
})();
</script>
