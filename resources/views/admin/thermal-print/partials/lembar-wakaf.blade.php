{{--
    Isi satu lembar wakaf (resi). Variabel: $pengiriman (model Pengiriman atau objek contoh).

    - Nama Wakif   : wakafItem.wakif_name (cadangan: nama donatur)
    - Tanggal Wakaf: pengiriman.tanggal_wakaf
    - Doa muwakif  : doa di database apa adanya, tanpa pilihan doa standar website
    - QR           : nomor resi, dibuat saat dicetak (tidak bergantung berkas QR di disk)
--}}
@php
    $noResi = (string) ($pengiriman->no_resi ?? '');
    $namaWakif = $pengiriman->wakafItem->wakif_name ?? null;
    $namaWakif = filled($namaWakif) ? $namaWakif : ($pengiriman->donatur->nama_donatur ?? '-');
    $tanggalWakaf = filled($pengiriman->tanggal_wakaf ?? null)
        ? \Illuminate\Support\Carbon::parse($pengiriman->tanggal_wakaf)->locale('id')->translatedFormat('d F Y')
        : '-';
    $doaMuwakif = \App\Models\WakafItem::doaUntukLembar($pengiriman->wakafItem->doa_request ?? null);
    $kodeDonatur = $pengiriman->donatur->kode_donatur ?? null;
    $jenis = $pengiriman->jenisQuran->nama_jenis ?? null;
@endphp
<div class="lembar">
    <div class="bingkai">
        <div class="kepala">
            <div class="logo"><img src="{{ asset('images/logo-alfatihah.png') }}" alt="alfatihah"><span class="dotcom">.com</span></div>
            <div class="judul">Penyaluran Wakaf Qur'an Untuk<br>Santri Masjid &amp; Penghafal Qur'an</div>
        </div>

        <div class="identitas">
            <div class="baris"><span class="label">Nama Wakif</span><span class="titik">:</span><span class="nilai">{{ $namaWakif }}</span></div>
            <div class="baris"><span class="label">Tanggal Wakaf</span><span class="titik">:</span><span class="nilai">{{ $tanggalWakaf }}</span></div>
        </div>

        <div class="doa-umum">
            <h2>Mohon didoakan orang - orang yang telah mewakafkan Al-qur'an</h2>
            <p>"Semoga setiap lantunan ayat yang dibaca dan dihafalkan dari Mushaf ini mengalirkan pahala jariyah, memberikan keberkahan serta syafaat bagi Para Muwakifnya, semoga dengan wasilah wakaf mushaf ini muwakif menjadi golongan ahli surga tanpa hisab aamiin."</p>
        </div>

        <div class="doa-muwakif">
            <h3>Mohon didoakan hajat dan cita-cita muwakif:</h3>
            @if($doaMuwakif)
                <div class="doa-isi">{{ $doaMuwakif }}</div>
            @endif
        </div>
    </div>

    <div class="resi">
        <div class="qr">
            @if($noResi !== '')
                {!! app(\App\Services\QrCodeService::class)->svgUntuk($noResi) !!}
            @endif
        </div>
        <div class="keterangan">
            <div class="no-resi">{{ $noResi !== '' ? $noResi : '-' }}</div>
            @if(filled($kodeDonatur))
                <div class="kode">DON {{ $kodeDonatur }}</div>
            @endif
            @if(filled($jenis))
                <div class="jenis">{{ $jenis }}</div>
            @endif
        </div>
    </div>
</div>
