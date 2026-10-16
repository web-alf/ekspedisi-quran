<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bulk Box Labels Thermal 100×150mm Portrait</title>
    <style>
        /* Custom thermal label 100×150 mm (Portrait) */
        @page {
            size: 100mm 150mm;
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
            width: 96mm;
            height: 146mm;
            border: 1px solid #000;
            padding: 1.5mm;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
            page-break-after: always;
        }
        
        .thermal-label:last-child {
            page-break-after: auto;
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
        
        /* Box Code + QR Combined Section */
        .box-qr-section {
            background: #f0f0f0;
            padding: 2mm;
            margin-bottom: 1mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .box-info {
            text-align: left;
        }
        
        .box-label {
            font-size: 9pt;
            color: #666;
            font-weight: bold;
        }
        
        .box-code {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.3px;
            margin: 0.5mm 0;
        }
        
        .box-date {
            font-size: 8pt;
            color: #666;
        }
        
        .box-qr-container {
            width: 25mm;
            height: 25mm;
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
        
        /* Info Table Compact */
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
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 2mm;
            font-size: 8pt;
            font-weight: bold;
        }
        
        .badge-sealed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-full {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .badge-filling {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .badge-empty {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        /* Contents List */
        .contents-list {
            font-size: 7pt;
            max-height: 40mm;
            overflow: hidden;
        }
        
        .content-item {
            padding: 0.5mm 0;
            border-bottom: 1px dashed #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-item:last-child {
            border-bottom: none;
        }
        
        .item-number {
            font-weight: bold;
            color: #666;
            margin-right: 2mm;
        }
        
        .item-resi {
            flex: 1;
            font-weight: bold;
        }
        
        .item-wakif {
            font-size: 6pt;
            color: #666;
            text-align: right;
            max-width: 30mm;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            
            .summary {
                background: white;
                padding: 20px;
                margin: 20px auto;
                max-width: 800px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="no-print preview-controls">
        <button onclick="window.print()" class="btn btn-primary">Print All ({{ $totalBoxes }} Labels)</button>
        <a href="{{ url()->previous() }}" class="btn btn-secondary">← Kembali</a>
    </div>
    
    <div class="no-print summary">
        <h3>Box Labels to Print: {{ $totalBoxes }}</h3>
        <p>Print Date: {{ $printDate }}, {{ $printTime }} WIB</p>
    </div>

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

    @foreach($boxes as $data)
        <div class="thermal-label">
            <!-- Header dengan Logo Full Width -->
            <div class="label-header">
                <div class="logo-container">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Ekspedisi Qur'an" class="logo" />
                    @else
                        <div class="logo" style="font-size: 12pt; font-weight: bold; text-align: center; line-height: 1;">EKSPEDISI QUR'AN</div>
                    @endif
                </div>
            </div>
            
            <!-- Box Code + QR Code Combined -->
            <div class="box-qr-section">
                <div class="box-info">
                    <div class="box-label">Kode Box:</div>
                    <div class="box-code">{{ $data['box']->kode_kerdus }}</div>
                    <div class="box-date">{{ $printDate }}, {{ $printTime }} WIB</div>
                </div>
                <div class="box-qr-container">
                    @if(isset($data['qrCodeBase64']))
                        <img src="{{ $data['qrCodeBase64'] }}" alt="Box QR Code" class="qr-code" />
                    @endif
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="label-content">
                <!-- Box Information -->
                <table class="section-table">
                    <tr>
                        <td class="section-header">Informasi Box</td>
                    </tr>
                    <tr>
                        <td class="section-content">
                            <table class="info-table">
                                <tr>
                                    <td class="label-col">Status:</td>
                                    <td class="value-col">
                                        <span class="status-badge badge-{{ $data['statusInfo']['color'] }}">
                                            {{ $data['statusInfo']['label'] }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">Jenis Quran:</td>
                                    <td class="value-col">{{ $data['contentSummary']['jenis_quran'] }}</td>
                                </tr>
                                <tr>
                                    <td class="label-col">Kapasitas:</td>
                                    <td class="value-col">{{ $data['contentSummary']['box_info']['terisi'] }} / {{ $data['contentSummary']['box_info']['kapasitas'] }} mushaf</td>
                                </tr>
                                <tr>
                                    <td class="label-col">Progress:</td>
                                    <td class="value-col">{{ $data['contentSummary']['box_info']['progress_percentage'] }}%</td>
                                </tr>
                                @if($data['box']->seal_code)
                                <tr>
                                    <td class="label-col">Seal Code:</td>
                                    <td class="value-col">{{ $data['box']->seal_code }}</td>
                                </tr>
                                @endif
                                @if($data['box']->sealed_at)
                                <tr>
                                    <td class="label-col">Sealed At:</td>
                                    <td class="value-col">{{ $data['contentSummary']['box_info']['sealed_at'] }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>
                
                <!-- Packed By -->
                <table class="section-table">
                    <tr>
                        <td class="section-header">Petugas Packing</td>
                    </tr>
                    <tr>
                        <td class="section-content">
                            <table class="info-table">
                                <tr>
                                    <td class="label-col">Nama:</td>
                                    <td class="value-col">{{ $data['box']->dailyPackingTask->user->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label-col">Tanggal Task:</td>
                                    <td class="value-col">{{ $data['box']->dailyPackingTask->assigned_date ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                
                <!-- Contents Summary -->
                <table class="section-table">
                    <tr>
                        <td class="section-header">Isi Box ({{ $data['contentSummary']['total_items'] }} Items)</td>
                    </tr>
                    <tr>
                        <td class="section-content">
                            <div class="contents-list">
                                @foreach($data['contentSummary']['items'] as $index => $item)
                                    <div class="content-item">
                                        <span class="item-number">#{{ $item['urutan_dalam_box'] }}</span>
                                        <span class="item-resi">{{ $item['no_resi'] }}</span>
                                        <span class="item-wakif">{{ $item['wakif'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="label-footer">
                Ekspedisi Quran - Box Label
            </div>
        </div>
    @endforeach
    
    <script>
        // Auto print untuk thermal printer
        function thermalPrint() {
            // Hapus kontrol preview untuk printing
            const controls = document.querySelector('.preview-controls');
            const summary = document.querySelector('.summary');
            if (controls) {
                controls.style.display = 'none';
            }
            if (summary) {
                summary.style.display = 'none';
            }
            
            // Print
            window.print();
            
            // Restore kontrol setelah print
            setTimeout(() => {
                if (controls) {
                    controls.style.display = 'block';
                }
                if (summary) {
                    summary.style.display = 'block';
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
    </script>
</body>
</html>