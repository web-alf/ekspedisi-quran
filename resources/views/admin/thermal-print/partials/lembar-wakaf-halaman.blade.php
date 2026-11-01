{{--
    Halaman cetak lembar wakaf. Dipakai label-a5 / label-a6 (satuan) dan bulk-a5 / bulk-a6 (massal).
    Variabel: $daftar (koleksi pengiriman), $kertas ('A5' | 'A6'), $judulHalaman.
--}}
@php
    $ukuranKertas = ['A5' => [148, 210, 1], 'A6' => [105, 148, 0.7095]][$kertas];
    [$lebar, $tinggi, $k] = $ukuranKertas;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judulHalaman }}</title>
    @include('admin.thermal-print.partials.lembar-wakaf-gaya', ['lebar' => $lebar, 'tinggi' => $tinggi, 'k' => $k])
</head>
<body>
    <div class="kontrol">
        <div class="pengingat">Kertas <b>{{ $kertas }}</b> · skala <b>100%</b> (Actual size, bukan "Fit to page") · margin <b>None/Default</b></div>
        <button type="button" onclick="window.print()">Cetak {{ $daftar->count() }} lembar</button>
        <a href="{{ url()->previous() }}">← Kembali</a>
    </div>

    @foreach($daftar as $pengiriman)
        @include('admin.thermal-print.partials.lembar-wakaf', ['pengiriman' => $pengiriman])
    @endforeach

    @include('admin.thermal-print.partials.lembar-wakaf-skrip')
</body>
</html>
