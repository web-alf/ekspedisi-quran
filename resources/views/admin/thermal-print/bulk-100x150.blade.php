<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Thermal Labels 100×150mm - {{ $totalLabels }} Labels</title>
    <style>
        @page {
            size: 100mm 150mm;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            height: {{ $totalLabels * 150 }}mm;
            overflow: hidden;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #000;
            line-height: 1.3;
            font-size: 9pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
            height: {{ $totalLabels * 150 }}mm;
            overflow: hidden;
        }
        
        .label-container {
            height: {{ $totalLabels * 150 }}mm;
            overflow: hidden;
        }
        
        .thermal-label {
            width: 100mm;
            height: 150mm;
            border: 1px solid #000;
            padding: 2mm;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: always;
        }
        
        .thermal-label:last-child {
            page-break-after: avoid !important;
        }
        
        .thermal-label:nth-child({{ $totalLabels }}) {
            page-break-after: avoid !important;
        }
        
        /* Header */
        .label-header {
            text-align: center;
            margin-bottom: 0.5mm;
            padding-bottom: 0.5mm;
            border-bottom: 2px solid #000;
        }
        
        .logo-container {
            width: 100%;
            height: 20mm;
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
        
        /* Resi + QR Section */
        .resi-qr-section {
            background: #f0f0f0;
            padding: 2mm;
            margin-bottom: 1mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .resi-info {
            text-align: left;
        }
        
        .resi-label {
            font-size: 9pt;
            color: #666;
            font-weight: bold;
        }
        
        .resi-number {
            font-size: 12pt;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.3px;
            margin: 0.5mm 0;
        }
        
        .resi-date {
            font-size: 8pt;
            color: #666;
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
        
        /* Main Content */
        .label-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 1mm;
        }
        
        /* Section */
        .section-table {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
            margin-bottom: 1mm;
        }
        
        .section-header {
            background: #f0f0f0;
            font-size: 9pt;
            font-weight: bold;
            color: #000;
            padding: 1.5mm;
            text-align: center;
            border-bottom: 1px solid #000;
        }
        
        .section-content {
            padding: 1mm;
        }
        
        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        
        .info-table td {
            padding: 0.8mm 1.5mm;
            vertical-align: top;
            border: none;
            line-height: 1.4;
        }
        
        .info-table .label-col {
            width: 22mm;
            font-weight: bold;
            color: #333;
            text-align: left;
            padding-left: 1mm;
        }
        
        .info-table .value-col {
            color: #000;
            word-break: break-word;
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
        
        /* Print optimizations */
        @media print {
            html {
                height: {{ $totalLabels * 150 }}mm !important;
                max-height: {{ $totalLabels * 150 }}mm !important;
                overflow: hidden !important;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                margin: 0 !important;
                padding: 0 !important;
                height: {{ $totalLabels * 150 }}mm !important;
                max-height: {{ $totalLabels * 150 }}mm !important;
                overflow: hidden !important;
            }
            
            .label-container {
                height: {{ $totalLabels * 150 }}mm !important;
                max-height: {{ $totalLabels * 150 }}mm !important;
                overflow: hidden !important;
            }
            
            .thermal-label {
                border: 1px solid #000 !important;
                margin: 0 !important;
            }
            
            .thermal-label:nth-child(n+{{ $totalLabels + 1 }}) {
                display: none !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Hide everything after the labels */
            body > *:not(.label-container):not(.thermal-label) {
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
                margin-bottom: 20px;
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
                background: #2c5530;
                color: white;
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
        }
    </style>
</head>
<body>
<div class="no-print preview-controls">
    <div class="print-summary">
        <strong>Print Summary</strong><br>
        Total Labels: {{ $totalLabels }}<br>
        Ukuran: 100×150mm<br>
        Tanggal: {{ $printDate }} {{ $printTime }}
    </div>
    <button onclick="window.print()" class="btn btn-primary">Print Semua</button>
    <a href="{{ url()->previous() }}" class="btn btn-secondary">← Kembali</a>
</div>
<div class="label-container">@foreach($pengirimanList as $pengiriman)<div class="thermal-label">
    <!-- Header dengan Logo -->
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
                <div class="logo" style="font-size: 12pt; font-weight: bold; text-align: center;">EKSPEDISI QUR'AN</div>
            @endif
        </div>
    </div>
    
    <!-- Resi + QR Code -->
    <div class="resi-qr-section">
        <div class="resi-info">
            <div class="resi-label">No:</div>
            <div class="resi-number">{{ $pengiriman->no_resi ?? 'EQ-2025-001234' }}</div>
            <div class="resi-date">{{ $printDate }}, {{ $printTime }} WIB</div>
        </div>
        <div class="resi-qr-container">
            @if($pengiriman->qr_code_path && file_exists(public_path('storage/' . str_replace('public/', '', $pengiriman->qr_code_path))))
                <img src="{{ asset('storage/' . str_replace('public/', '', $pengiriman->qr_code_path)) }}" alt="QR Code" class="qr-code" />
            @else
                <div class="qr-placeholder">QR</div>
            @endif
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="label-content">
        <!-- Pengirim -->
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
                            <td class="label-col">Resi:</td>
                            <td class="value-col">{{ $pengiriman->no_resi ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Tanggal:</td>
                            <td class="value-col">{{ $printDate }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Alamat:</td>
                            <td class="value-col">{{ $contactAddress }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <!-- Donatur & Wakif -->
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
                        <tr>
                            <td class="label-col">Wakif:</td>
                            <td class="value-col">{{ $pengiriman->wakafItem->wakif_name ?? $pengiriman->donatur->nama_donatur ?? 'N/A' }}</td>
                        </tr>
                        @if(isset($pengiriman->wakafItem->doa_request) && $pengiriman->wakafItem->doa_request)
                        <tr>
                            <td class="label-col">Doa:</td>
                            <td class="value-col">{{ $pengiriman->wakafItem->doa_request }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
        
        <!-- Jenis Wakaf -->
        <table class="section-table">
            <tr>
                <td class="section-header">Jenis Wakaf</td>
            </tr>
            <tr>
                <td class="section-content">
                    <table class="info-table">
                        <tr>
                            <td class="label-col">Jenis:</td>
                            <td class="value-col">{{ $pengiriman->jenisQuran->nama_jenis ?? 'Al-Quran Ukuran A5' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>@endforeach</div>
<script>
// Ensure only exact number of labels are printed
window.addEventListener('beforeprint', function() {
    const labels = document.querySelectorAll('.thermal-label');
    const totalLabels = {{ $totalLabels }};
    
    // Hide any labels beyond the expected count
    for (let i = totalLabels; i < labels.length; i++) {
        labels[i].style.display = 'none';
    }
    
    // Set exact height to prevent extra pages
    document.body.style.height = (totalLabels * 150) + 'mm';
    document.body.style.maxHeight = (totalLabels * 150) + 'mm';
    document.documentElement.style.height = (totalLabels * 150) + 'mm';
    document.documentElement.style.maxHeight = (totalLabels * 150) + 'mm';
    
    // Remove any content after labels
    const container = document.querySelector('.label-container');
    if (container) {
        container.style.height = (totalLabels * 150) + 'mm';
        container.style.maxHeight = (totalLabels * 150) + 'mm';
        container.style.overflow = 'hidden';
    }
});
</script>
</body>
</html>