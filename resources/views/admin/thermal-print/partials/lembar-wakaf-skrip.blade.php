{{-- Doa yang terlalu panjang untuk kotaknya dikecilkan hurufnya sampai muat (minimal 55%),
     supaya doa tetap tercetak utuh seperti di database. Tanpa JavaScript huruf tetap normal. --}}
<script>
(function () {
    function muatkan(kotak) {
        const isi = kotak.querySelector('.doa-isi');
        if (!isi) {
            return;
        }
        isi.style.fontSize = '';
        const dasar = parseFloat(getComputedStyle(isi).fontSize);
        const batas = () => kotak.scrollHeight <= kotak.clientHeight + 1;
        for (let skala = 1; skala >= 0.55 && !batas(); skala -= 0.03) {
            isi.style.fontSize = (dasar * skala) + 'px';
        }
    }

    function muatkanSemua() {
        document.querySelectorAll('.doa-muwakif').forEach(muatkan);
    }

    muatkanSemua();
    window.addEventListener('load', muatkanSemua);
    window.addEventListener('beforeprint', muatkanSemua);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(muatkanSemua);
    }
})();
</script>
