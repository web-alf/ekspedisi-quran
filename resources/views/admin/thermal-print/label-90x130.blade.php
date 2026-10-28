<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Label Thermal 90×130mm Portrait - {{ $pengiriman->no_resi ?? 'Preview' }}</title>
    <style>
        /* Label thermal 90×130 mm (Portrait) untuk mushaf bersampul kecil (A6, sampul 14 × 10 cm);
           ukuran dipilih otomatis lewat App\Enums\UkuranLabelResi. */
        @page {
            size: 90mm 130mm;
            margin: 2mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: white;
            color: #000;
            line-height: 1.3;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .thermal-label {
            width: 86mm;
            height: 126mm;
            border: 1px solid #000;
            padding: 1.5mm;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
            /* Isi yang kelebihan tidak boleh keluar kotak dan menimpa label berikutnya. */
            overflow: hidden;
        }
        
        /* Header Compact */
        .label-header {
            text-align: center;
            margin-bottom: 0.5mm;
            padding-bottom: 0.5mm;
            border-bottom: 2px solid #000000 !important;
        }
        
        .logo-container {
            width: 100%;
            height: 14mm;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .company-address {
            font-size: 7pt;
            color: #333;
            line-height: 1.3;
            text-align: center;
            font-weight: bold;
        }
        
        /* Resi + QR Combined Section with Space Between */
        .resi-qr-section {
            background: #f0f0f0;
            padding: 1.5mm;
            margin-bottom: 1mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
        }
        
        .resi-info {
            text-align: left;
        }
        
        .resi-label {
            font-size: 7pt;
            color: #666;
            font-weight: bold;
        }
        
        .resi-number {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.3px;
            margin: 0.5mm 0;
        }
        
        .resi-date {
            font-size: 7pt;
            color: #666;
        }

        /* Jenis mushaf tampil di kotak resi (pengganti seksi "Jenis Wakaf" pada label 10 × 15 cm). */
        .jenis-badge {
            display: inline-block;
            margin-top: 1mm;
            border: 1.5px solid #000;
            padding: 0.3mm 1.5mm;
            font-size: 9pt;
            background: white;
        }
        
        .resi-qr-container {
            width: 20mm;
            height: 20mm;
            border: 1px solid #000;
            padding: 0.5mm;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        /* Main Content Compact */
        .label-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 1mm;
        }
        
        /* Section Compact */
        .section-table {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
            /* Jarak antarseksi cukup dari gap .label-content (dirapatkan 28/10/2026 supaya
               nama/alamat panjang + doa panjang tetap muat di 130 mm). */
            margin-bottom: 0;
        }
        
        .section-header {
            background: #f0f0f0;
            font-size: 8pt;
            font-weight: bold;
            color: #000;
            padding: 0.7mm;
            text-align: center;
            border-bottom: 1px solid #000;
        }
        
        .section-content {
            padding: 0.5mm;
        }
        
        /* Info Table Compact */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        
        .info-table td {
            padding: 0.4mm 1mm;
            vertical-align: top;
            border: none;
            line-height: 1.25;
        }
        
        .info-table .label-col {
            /* 16mm membuat "HP Donatur:" patah dua baris di label 90 mm sehingga
               tampak seperti baris "Donatur:" kosong (temuan 28/10/2026). */
            width: 19mm;
            font-weight: bold;
            color: #333;
            text-align: left;
            padding-left: 0.8mm;
            white-space: nowrap;
        }
        
        .info-table .value-col {
            color: #000;
            word-break: break-word;
            font-weight: bold;
        }

        /* Doa: maks. 250 karakter (WakafItem::DOA_MAKS_KARAKTER); font mengecil bertingkat
           menurut panjangnya (WakafItem::kelasUkuranDoaLabel) agar tetap muat di label.
           Doa lama yang lebih panjang dipotong dengan "…" di server (28/10/2026). */
        .info-table .doa-col {
            font-size: 7pt;
            line-height: 1.25;
        }

        .info-table .doa-col.doa-sedang {
            font-size: 6.5pt;
        }

        .info-table .doa-col.doa-panjang {
            font-size: 6pt;
            line-height: 1.2;
        }
        
        .address-content {
            padding: 1.5mm;
            line-height: 1.5;
            font-size: 8pt;
            font-weight: bold;
        }
        
        .qr-code {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .qr-placeholder {
            font-size: 6pt;
            text-align: center;
            color: #666;
            line-height: 1.2;
            font-weight: bold;
        }
        
        .wakif-name {
            font-weight: bold;
        }
        
        .wakif-name {
            font-weight: bold;
        }
        
        
        
        /* Footer */
        .label-footer {
            margin-top: auto;
            border-top: 1px solid #000;
            padding-top: 2mm;
            text-align: center;
            font-size: 9pt;
            color: #666;
            font-weight: bold;
        }
        
        /* Print optimizations */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .thermal-label {
                border: 2px solid #000 !important;
            }
            
            .label-header {
                border-bottom: 2px solid #000000 !important;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Preview mode */
        @media screen {
            body {
                padding: 20px;
                background: #f0f0f0;
            }
            
            .thermal-label {
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: scale(0.8);
                margin: 20px auto;
            }
            
            .preview-controls {
                position: fixed;
                top: 10px;
                right: 10px;
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 1000;
            }
            
            .btn {
                padding: 8px 16px;
                margin-left: 8px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                text-decoration: none;
                display: inline-block;
            }
            
            .btn-primary {
                background: #007bff;
                color: white;
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            
            .btn:hover {
                opacity: 0.9;
            }
        }
    </style>
</head>
<body>
    @if(!isset($isPreview) || !$isPreview)
        <div class="no-print preview-controls">
            <button onclick="window.print()" class="btn btn-primary">Print</button>
            <a href="{{ url()->previous() }}" class="btn btn-secondary">← Kembali</a>
        </div>
    @endif

    <div class="thermal-label">
        <!-- Header dengan Logo Full Width -->
        <div class="label-header">
            @php
                $logoUrl = null;
                
                if ($appLogo) {
                    // Handle different path formats
                    if (str_starts_with($appLogo, 'http://') || str_starts_with($appLogo, 'https://')) {
                        $logoUrl = $appLogo;
                    } elseif (str_starts_with($appLogo, '/')) {
                        $logoUrl = asset(ltrim($appLogo, '/'));
                    } else {
                        // For paths like 'settings/logo.webp'
                        $logoUrl = asset('storage/' . $appLogo);
                    }
                }
            @endphp
            
            <div class="logo-container">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Ekspedisi Qur'an" class="logo" />
                @else
                    <div class="logo" style="font-size: 12pt; font-weight: bold; text-align: center; line-height: 1;">EKSPEDISI QUR'AN</div>
                @endif
            </div>
        </div>
        
        <!-- Resi + QR Code Combined -->
        <div class="resi-qr-section">
            <div class="resi-info">
                <div class="resi-label">No:</div>
                <div class="resi-number">{{ $pengiriman->no_resi ?? 'EQ-2025-001234' }}</div>
                <div class="resi-date">{{ $printDate }}, {{ $printTime }} WIB</div>
                <div class="jenis-badge">{{ $pengiriman->jenisQuran->nama_jenis ?? 'Al-Quran A6' }}</div>
            </div>
            <div class="resi-qr-container">
                @if($qrUrl && file_exists(public_path('storage/' . str_replace('public/', '', $pengiriman->qr_code_path ?? ''))))
                    <img src="{{ $qrUrl }}" alt="QR Code" class="qr-code" />
                @elseif(isset($pengiriman->no_resi))
                    <div class="qr-placeholder">
                        <div style="font-size: 4pt; font-weight: bold; margin-bottom: 0.5mm;">QR</div>
                        <div style="font-size: 3pt;">{{ substr($pengiriman->no_resi, -4) }}</div>
                    </div>
                @else
                    <div class="qr-placeholder">
                        <div style="font-size: 3pt;">QR</div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="label-content">
            <!-- Pengirim (with address) -->
            <table class="section-table">
                <tr>
                    <td class="section-header">Pengirim</td>
                </tr>
                <tr>
                    <td class="section-content">
                        <table class="info-table">
                            <tr>
                                <td class="label-col">Nama:</td>
                                <td class="value-col">Ekspedisi Quran</td>
                            </tr>
                            <tr>
                                <td class="label-col">Alamat:</td>
                                <td class="value-col">{{ $contactAddress }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            
            <!-- Donatur & Wakif (reordered with doa) -->
            <table class="section-table">
                <tr>
                    <td class="section-header">Donatur & Wakif</td>
                </tr>
                <tr>
                    <td class="section-content">
                        <table class="info-table">
                            <tr>
                                <td class="label-col">Donatur:</td>
                                <td class="value-col">{{ $pengiriman->donatur->nama_donatur ?? 'N/A' }} @if($pengiriman->donatur && $pengiriman->donatur->kode_donatur)({{ $pengiriman->donatur->kode_donatur }})@endif</td>
                            </tr>
                            @if($pengiriman->donatur && isset($pengiriman->donatur->no_hp) && $pengiriman->donatur->no_hp)
                            <tr>
                                <td class="label-col">HP Donatur:</td>
                                <td class="value-col">
                                    @php
                                        $donaturHp = $pengiriman->donatur->no_hp;
                                        $maskedDonaturHp = strlen($donaturHp) > 4 ? substr($donaturHp, 0, -4) . '****' : $donaturHp;
                                    @endphp
                                    {{ $maskedDonaturHp }}
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label-col">Wakif:</td>
                                <td class="value-col wakif-name">{{ $pengiriman->wakafItem->wakif_name ?? $pengiriman->donatur->nama_donatur ?? 'N/A' }}</td>
                            </tr>
                            @if($pengiriman->wakafItem && isset($pengiriman->wakafItem->wakif_phone) && $pengiriman->wakafItem->wakif_phone)
                            <tr>
                                <td class="label-col">HP Wakif:</td>
                                <td class="value-col">
                                    @php
                                        $wakifHp = $pengiriman->wakafItem->wakif_phone;
                                        $maskedWakifHp = strlen($wakifHp) > 4 ? substr($wakifHp, 0, -4) . '****' : $wakifHp;
                                    @endphp
                                    {{ $maskedWakifHp }}
                                </td>
                            </tr>
                            @endif
                            @if(isset($pengiriman->wakafItem->doa_request) && $pengiriman->wakafItem->doa_request)
                            <tr>
                                <td class="label-col">Doa:</td>
                                <td class="value-col doa-col {{ \App\Models\WakafItem::kelasUkuranDoaLabel($pengiriman->wakafItem->doa_request) }}">{{ \App\Models\WakafItem::doaUntukLabel($pengiriman->wakafItem->doa_request) }}</td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>

        </div>
    </div>
    
    <script>
        // Auto print untuk thermal printer
        function thermalPrint() {
            // Hapus kontrol preview untuk printing
            const controls = document.querySelector('.preview-controls');
            if (controls) {
                controls.style.display = 'none';
            }
            
            // Print
            window.print();
            
            // Restore kontrol setelah print
            setTimeout(() => {
                if (controls) {
                    controls.style.display = 'block';
                }
            }, 1000);
        }
        
        // Keyboard shortcut untuk print
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                thermalPrint();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
        
        @if(isset($isPreview) && $isPreview)
            // Auto print untuk preview mode
            setTimeout(() => {
                if(confirm('Cetak label thermal sekarang?')) {
                    thermalPrint();
                }
            }, 500);
        @endif
    </script>
</body>
</html>