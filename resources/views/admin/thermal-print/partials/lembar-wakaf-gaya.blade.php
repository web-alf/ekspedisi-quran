{{--
    Gaya lembar wakaf (resi) — satu sumber untuk kertas A5 dan A6.

    Tata letak mengikuti lembar wakaf alfatihah.com (keputusan pemilik 30/10/2026). Semua
    ukuran ditulis untuk A5 (148 × 210 mm) lalu dikali --k: A5 = 1, A6 = 105/148 ≈ 0,709,
    sehingga lembar A6 sama persis dengan A5, hanya lebih kecil.

    Variabel: $lebar, $tinggi (mm, ukuran kertas), $k (faktor skala).
--}}
<style>
    @font-face { font-family: 'Mali'; font-weight: 500; src: url('{{ asset('data/fonts/mali-500.woff2') }}') format('woff2'); }
    @font-face { font-family: 'Mali'; font-weight: 600; src: url('{{ asset('data/fonts/mali-600.woff2') }}') format('woff2'); }
    @font-face { font-family: 'Mali'; font-weight: 700; src: url('{{ asset('data/fonts/mali-700.woff2') }}') format('woff2'); }
    @font-face { font-family: 'Nunito'; font-weight: 800; src: url('{{ asset('data/fonts/nunito-800.woff2') }}') format('woff2'); }

    @page { size: {{ $lebar }}mm {{ $tinggi }}mm; margin: 0; }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { background: #fff; color: #1a1a1a; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    .lembar {
        --k: {{ $k }};
        position: relative;
        width: {{ $lebar }}mm;
        height: {{ $tinggi }}mm;
        overflow: hidden;
        background: #fff;
        page-break-after: always;
        break-after: page;
    }
    .lembar:last-child { page-break-after: auto; break-after: auto; }

    .bingkai {
        position: absolute;
        left: calc(8mm * var(--k)); top: calc(12mm * var(--k));
        width: calc(132mm * var(--k)); height: calc(135mm * var(--k));
        border: calc(0.55mm * var(--k)) solid #2b2b2b; border-radius: calc(6mm * var(--k));
        padding: calc(4.5mm * var(--k)) calc(4mm * var(--k)) calc(4mm * var(--k));
        display: flex; flex-direction: column;
    }

    .kepala {
        display: flex; align-items: center; justify-content: space-between;
        padding: calc(1.5mm * var(--k)) calc(7mm * var(--k)) 0 calc(4mm * var(--k));
        height: calc(14mm * var(--k)); flex: none;
    }
    .logo { display: flex; align-items: flex-end; height: calc(7.4mm * var(--k)); }
    .logo img { height: calc(7.4mm * var(--k)); width: auto; }
    .logo .dotcom {
        font-family: 'Nunito', Arial, sans-serif; font-weight: 800; font-size: calc(11pt * var(--k));
        color: #4a4a4a; line-height: 1; margin-left: calc(0.2mm * var(--k)); margin-bottom: calc(0.45mm * var(--k));
    }
    .judul {
        font-family: 'Mali', Arial, sans-serif; font-weight: 700; font-size: calc(11.2pt * var(--k));
        line-height: 1.25; text-align: center; color: #262626;
    }

    .identitas {
        margin: calc(2.2mm * var(--k)) calc(4.5mm * var(--k)) 0; flex: none;
        border: calc(0.35mm * var(--k)) solid #2b2b2b; border-radius: calc(3.5mm * var(--k));
        padding: calc(2.6mm * var(--k)) calc(4mm * var(--k)) calc(2.2mm * var(--k));
        box-shadow: calc(0.25mm * var(--k)) calc(0.35mm * var(--k)) 0 #2b2b2b;
    }
    .baris { display: flex; align-items: baseline; font-size: calc(9.2pt * var(--k)); line-height: 1.9; }
    .baris .label { font-family: 'Mali', Arial, sans-serif; font-weight: 600; width: calc(27mm * var(--k)); flex: none; color: #303030; }
    .baris .titik { font-family: 'Mali', Arial, sans-serif; font-weight: 700; width: calc(4mm * var(--k)); flex: none; }
    .baris .nilai { font-family: 'Mali', Arial, sans-serif; font-weight: 700; color: #222; overflow-wrap: anywhere; }

    .doa-umum { margin: calc(3.3mm * var(--k)) calc(4.5mm * var(--k)) 0; font-family: 'Mali', Arial, sans-serif; color: #262626; flex: none; }
    .doa-umum h2 { font-size: calc(9.4pt * var(--k)); font-weight: 700; margin-bottom: calc(1.2mm * var(--k)); }
    .doa-umum p { font-size: calc(8.9pt * var(--k)); font-weight: 700; line-height: 1.12; }

    .doa-muwakif {
        margin-top: calc(3.6mm * var(--k)); flex: 1; min-height: 0; overflow: hidden;
        border: calc(0.55mm * var(--k)) solid #2b2b2b; border-radius: calc(3.5mm * var(--k));
        padding: calc(3mm * var(--k)) calc(4mm * var(--k));
        box-shadow: calc(-0.4mm * var(--k)) calc(0.55mm * var(--k)) 0 #2b2b2b;
    }
    .doa-muwakif h3 { font-family: 'Mali', Arial, sans-serif; font-size: calc(9.4pt * var(--k)); font-weight: 700; margin-bottom: calc(1.2mm * var(--k)); color: #262626; }
    /* Doa dicetak persis seperti di database: baris baru dipertahankan, tidak dipotong.
       Doa yang sangat panjang dikecilkan hurufnya oleh partials/lembar-wakaf-skrip. */
    .doa-muwakif .doa-isi {
        font-family: 'Mali', Arial, sans-serif; font-weight: 700; font-size: calc(9.5pt * var(--k)); line-height: 1.25;
        color: #222; white-space: pre-line; overflow-wrap: anywhere;
    }

    /* Bagian resi: QR (isi = nomor resi) untuk dipindai gudang, kode donatur, jenis mushaf. */
    .resi {
        position: absolute;
        left: calc(8mm * var(--k)); right: calc(8mm * var(--k)); top: calc(156mm * var(--k));
        display: flex; align-items: center; gap: calc(3mm * var(--k));
    }
    .resi .qr {
        width: calc(20mm * var(--k)); height: calc(20mm * var(--k)); flex: none;
        border: calc(0.3mm * var(--k)) solid #2b2b2b; border-radius: calc(1.5mm * var(--k));
        padding: calc(0.6mm * var(--k)); background: #fff;
    }
    .resi .qr svg { display: block; width: 100%; height: 100%; }
    .resi .keterangan { display: flex; flex-direction: column; gap: calc(1mm * var(--k)); min-width: 0; }
    .resi .no-resi { font-family: Arial, sans-serif; font-weight: bold; font-size: calc(8.5pt * var(--k)); letter-spacing: calc(0.1mm * var(--k)); }
    .resi .kode {
        align-self: flex-start;
        border: calc(0.3mm * var(--k)) solid #2b2b2b; border-radius: calc(1.2mm * var(--k));
        padding: calc(0.8mm * var(--k)) calc(2.5mm * var(--k));
        font-family: Arial, sans-serif; font-size: calc(7.5pt * var(--k)); color: #222; letter-spacing: calc(0.1mm * var(--k));
    }
    .resi .jenis { font-family: Arial, sans-serif; font-size: calc(6.5pt * var(--k)); color: #333; }

    .kontrol { display: none; }

    @media screen {
        body { background: #9a9a9a; padding: 10mm 0; }
        .lembar { margin: 0 auto 8mm; box-shadow: 0 2mm 6mm rgba(0, 0, 0, .35); }
        .kontrol {
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
            position: fixed; top: 10px; right: 10px; z-index: 10;
            background: #fff; padding: 10px 12px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, .15);
            font: 13px/1.4 Arial, sans-serif; max-width: 360px;
        }
        .kontrol .pengingat { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; border-radius: 4px; padding: 6px 8px; }
        .kontrol button, .kontrol a { padding: 8px 14px; border: 0; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .kontrol button { background: #007bff; color: #fff; }
        .kontrol a { background: #6c757d; color: #fff; }
    }
</style>
