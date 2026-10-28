<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pilih Ukuran Label - Cetak Massal</title>
    <style>
        /* Resi terpilih campuran ukuran label (A5/Iqro 10 × 15 cm, A6 9 × 13 cm). Printer thermal
           hanya memuat satu ukuran rol, jadi setiap ukuran dicetak terpisah (28/10/2026). */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #1f2937;
            padding: 24px 16px;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        h1 {
            font-size: 24px;
        }

        .subjudul {
            margin-top: 4px;
            color: #4b5563;
        }

        .pengingat {
            background: #fff7e0;
            border: 1px solid #e8c766;
            border-radius: 10px;
            padding: 12px 16px;
            color: #4d3800;
            line-height: 1.5;
        }

        .daftar-ukuran {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .kartu-ukuran {
            background: white;
            border: 1px solid #dcdce0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .kepala-kartu {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .rol {
            font-size: 13px;
            font-weight: 700;
            color: #4b5563;
        }

        .ukuran {
            font-size: 22px;
            font-weight: 700;
        }

        .jumlah {
            font-size: 30px;
            font-weight: 700;
        }

        .jenis-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .jenis {
            font-size: 13px;
            font-weight: 700;
            background: #ececef;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .resi-list {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.5;
            word-break: break-word;
        }

        .btn-cetak {
            min-height: 44px;
            padding: 0 18px;
            border: 0;
            border-radius: 8px;
            background: #b42323;
            color: white;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-cetak:hover {
            background: #8a1a1a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div>
            <h1>Cetak Label Massal</h1>
            <p class="subjudul">{{ $totalDipilih }} resi dipilih, terdiri dari {{ $kelompokUkuran->count() }} ukuran label.</p>
        </div>

        <p class="pengingat">Printer thermal hanya memuat satu ukuran rol. Cetak satu ukuran dulu, ganti rol stiker, lalu cetak ukuran berikutnya.</p>

        <div class="daftar-ukuran">
            @foreach($kelompokUkuran as $kelompok)
                <form method="POST" action="{{ route('admin.thermal-print.bulk') }}" target="_blank" class="kartu-ukuran">
                    @csrf
                    <input type="hidden" name="ukuran" value="{{ $kelompok['ukuran']->value }}">
                    @foreach($kelompok['pengirimanList'] as $pengiriman)
                        <input type="hidden" name="pengiriman_ids[]" value="{{ $pengiriman->id }}">
                    @endforeach

                    <div class="kepala-kartu">
                        <div>
                            <div class="rol">Rol stiker {{ $kelompok['ukuran']->rolStiker() }}</div>
                            <div class="ukuran">Label {{ $kelompok['ukuran']->label() }}</div>
                        </div>
                        <div class="jumlah">{{ $kelompok['pengirimanList']->count() }}</div>
                    </div>

                    <div class="jenis-list">
                        @foreach($kelompok['pengirimanList']->groupBy(fn ($pengiriman) => $pengiriman->jenisQuran->nama_jenis ?? 'Tanpa jenis') as $namaJenis => $daftar)
                            <span class="jenis">{{ $namaJenis }} · {{ $daftar->count() }}</span>
                        @endforeach
                    </div>

                    <p class="resi-list">{{ $kelompok['pengirimanList']->pluck('no_resi')->filter()->implode(', ') }}</p>

                    <div>
                        <button type="submit" class="btn-cetak">Cetak {{ $kelompok['pengirimanList']->count() }} label {{ $kelompok['ukuran']->label() }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</body>
</html>
