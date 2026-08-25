<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Label Box Thermal 100×150mm Portrait - {{ $box->kode_kerdus }}</title>
    <style>
        /* Custom thermal label 100×150 mm (Portrait) */
        @page {
            size: 100mm 150mm;
            margin: 1mm;
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
            width: 98mm;
            height: 148mm;
            border: 2px solid #000;
            padding: 1mm;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* Header Compact */
        .label-header {
            text-align: center;
            margin-bottom: 1mm;
            padding-bottom: 1mm;
            border-bottom: 2px solid #000000 !important;
        }
        
        .logo-container {
            width: 100%;
            height: 15mm;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
        
        .logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Box Code + QR Combined Section */
        .box-qr-section {
            background: #f0f0f0;
            padding: 1.5mm;
            margin-bottom: 0.5mm;
            border: 2px solid #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .box-info {
            text-align: left;
        }
        
        .box-label {
            font-size: 11pt;
            color: #666;
            font-weight: bold;
        }
        
        .box-code {
            font-size: 16pt;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.2px;
            margin: 0.3mm 0;
        }
        
        .box-date {
            font-size: 10pt;
            color: #666;
        }
        
        .box-qr-container {
            width: 20mm;
            height: 20mm;
            border: 2px solid #000;
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
            gap: 0.5mm;
        }
        
        /* Section Compact */
        .section-table {
            width: 100%;
            border: 2px solid #000;
            border-collapse: collapse;
            margin-bottom: 0.5mm;
        }
        
        .section-header {
            background: #e9ecef;
            font-size: 12pt;
            font-weight: bold;
            color: #000;
            padding: 1mm;
            text-align: center;
            border-bottom: 2px solid #000;
        }
        
        .section-content {
            padding: 0.5mm;
        }
        
        /* Info Table Compact */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        
        .info-table td {
            padding: 0.5mm 1mm;
            vertical-align: top;
            border: none;
            line-height: 1.2;
        }
        
        .info-table .label-col {
            width: 24mm;
            font-weight: bold;
            color: #333;
            text-align: left;
            padding-left: 1mm;
            font-size: 10pt;
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
            font-size: 10pt;
            font-weight: bold;
            line-height: 1;
        }
        /* Align status row label and value to middle for better visual alignment */
        .info-table .status-row td { vertical-align: middle; }
        
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
            font-size: 9pt;
            max-height: 45mm;
            overflow: hidden;
        }
        
        .content-item {
            padding: 0.3mm 0;
            border-bottom: 1px dashed #ccc;
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
            font-size: 8pt;
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
            border-top: 2px solid #000;
            padding-top: 1mm;
            text-align: center;
            font-size: 12pt;
            color: #000;
            font-weight: bold;
            background: #f8f9fa;
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
    <div class="no-print preview-controls">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        @if(isset($isPreview) && $isPreview)
            <a href="{{ route('admin.box-tracking.index') }}" class="btn btn-secondary">← Kembali ke Box Tracking</a>
        @else
            <a href="{{ url()->previous() }}" class="btn btn-secondary">← Kembali</a>
        @endif
        @if(isset($isPreview) && $isPreview)
            <span class="btn" style="background: #ffc107; color: #000; pointer-events: none;">MODE PREVIEW</span>
        @endif
    </div>

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
                    <div class="logo" style="font-size: 14pt; font-weight: bold; text-align: center; line-height: 1.1; color: #000; padding: 1mm;">
                        <div style="font-size: 16pt; margin-bottom: 1mm;">EKSPEDISI QUR'AN</div>
                        <div style="font-size: 10pt; color: #666;">LABEL KERDUS</div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Box Code + QR Code Combined -->
        <div class="box-qr-section">
            <div class="box-info">
                <div class="box-label">Kode Box:</div>
                <div class="box-code">{{ $box->kode_kerdus }}</div>
                <div class="box-date">{{ $printDate }}, {{ $printTime }} WIB</div>
            </div>
            <div class="box-qr-container">
                @if(isset($qrCodeBase64))
                    <img src="{{ $qrCodeBase64 }}" alt="Box QR Code" class="qr-code" />
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
                            <tr class="status-row">
                                <td class="label-col">Status:</td>
                                <td class="value-col">
                                    <span class="status-badge badge-{{ $statusInfo['color'] }}">
                                        {{ $statusInfo['label'] }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">Jenis Quran:</td>
                                <td class="value-col">{{ $contentSummary['jenis_quran'] }}</td>
                            </tr>
                            <tr>
                                <td class="label-col">Kapasitas:</td>
                                <td class="value-col">{{ $contentSummary['box_info']['terisi'] }} / {{ $contentSummary['box_info']['kapasitas'] }} mushaf</td>
                            </tr>
                            <tr>
                                <td class="label-col">Progress:</td>
                                <td class="value-col">{{ $contentSummary['box_info']['progress_percentage'] }}%</td>
                            </tr>
                            @if($box->seal_code)
                            <tr>
                                <td class="label-col">Seal Code:</td>
                                <td class="value-col">{{ $box->seal_code }}</td>
                            </tr>
                            @endif
                            @if($box->sealed_at)
                            <tr>
                                <td class="label-col">Sealed At:</td>
                                <td class="value-col">{{ $contentSummary['box_info']['sealed_at'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
            
            <!-- Petugas Packing -->
            <table class="section-table">
                <tr>
                    <td class="section-header">Petugas Packing</td>
                </tr>
                <tr>
                    <td class="section-content">
                        <table class="info-table">
                            <tr>
                                <td class="label-col">Nama:</td>
                                <td class="value-col">{{ $box->dailyPackingTask->user->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label-col">Tanggal:</td>
                                <td class="value-col">
                                    @php
                                        $taskDate = null;
                                        if ($box->dailyPackingTask?->tanggal_tugas instanceof \Carbon\CarbonInterface) {
                                            $taskDate = $box->dailyPackingTask->tanggal_tugas->format('Y-m-d');
                                        } elseif (!empty($box->dailyPackingTask?->tanggal_tugas)) {
                                            $taskDate = (string) $box->dailyPackingTask->tanggal_tugas;
                                        } elseif ($box->dailyPackingTask?->assigned_at instanceof \Carbon\CarbonInterface) {
                                            $taskDate = $box->dailyPackingTask->assigned_at->format('Y-m-d H:i');
                                        } elseif (!empty($box->dailyPackingTask?->assigned_at)) {
                                            $taskDate = (string) $box->dailyPackingTask->assigned_at;
                                        } elseif ($box->created_at instanceof \Carbon\CarbonInterface) {
                                            $taskDate = $box->created_at->format('Y-m-d');
                                        } elseif (!empty($box->created_at)) {
                                            $taskDate = (string) $box->created_at;
                                        }
                                    @endphp
                                    {{ $taskDate ?: 'N/A' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            
            <!-- Contents Summary removed as requested -->
        </div>
        
        <!-- Footer -->
        <div class="label-footer">
            Ekspedisi Quran - Box Label
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
    </script>
</body>
</html>
