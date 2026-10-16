<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bulk Thermal Print - Admin Ekspedisi Qur'an</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 5px;
            font-size: 24px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 20px;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-item {
            text-align: center;
        }
        
        .stats-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stats-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .pengiriman-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .pengiriman-table th,
        .pengiriman-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .pengiriman-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        
        .pengiriman-table tr:hover {
            background: #f7fafc;
        }
        
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-packing {
            background: #fef2e2;
            color: #f6ad55;
        }
        
        .status-shipping {
            background: #e6fffa;
            color: #4fd1c7;
        }
        
        .status-done {
            background: #f0fff4;
            color: #68d391;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        
        .selected-count {
            color: #667eea;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .pengiriman-table {
                font-size: 12px;
            }
            
            .pengiriman-table th,
            .pengiriman-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖨️ Bulk Thermal Print</h1>
            <p>Pilih pengiriman untuk di-print label thermal secara massal</p>
        </div>
        
        <div class="content">
            <div class="stats">
                <div class="stats-item">
                    <div class="stats-number">{{ $totalAvailable }}</div>
                    <div class="stats-label">Pengiriman Tersedia</div>
                </div>
                <div class="stats-item">
                    <div class="stats-number" id="selectedCount">0</div>
                    <div class="stats-label">Dipilih</div>
                </div>
                <div class="stats-item">
                    <div class="stats-number">100×150mm</div>
                    <div class="stats-label">Ukuran Label</div>
                </div>
            </div>
            
            <div class="controls">
                <button type="button" class="btn btn-secondary" onclick="selectAll()">
                    Pilih Semua
                </button>
                <button type="button" class="btn btn-secondary" onclick="selectNone()">
                    Batal Pilih
                </button>
                <button type="button" class="btn btn-primary" onclick="previewSelected()" id="previewBtn" disabled>
                    👁️ Preview Label
                </button>
                <button type="button" class="btn btn-success" onclick="printSelected()" id="printBtn" disabled>
                    🖨️ Print Bulk Label
                </button>
                <span class="selected-count" id="selectedText">0 pengiriman dipilih</span>
            </div>
            
            @if($pengirimanList->count() > 0)
                <form id="bulkPrintForm">
                    @csrf
                    <table class="pengiriman-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="checkAll" onchange="toggleAll()">
                                </th>
                                <th>No. Resi</th>
                                <th>Donatur</th>
                                <th>Wakif</th>
                                <th>Jenis Quran</th>
                                <th>Status</th>
                                <th>QR Code</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pengirimanList as $pengiriman)
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" 
                                               name="pengiriman_ids[]" 
                                               value="{{ $pengiriman->id }}" 
                                               class="pengiriman-checkbox"
                                               onchange="updateSelectedCount()">
                                    </td>
                                    <td><strong>{{ $pengiriman->no_resi }}</strong></td>
                                    <td>{{ $pengiriman->donatur->nama_donatur }}</td>
                                    <td>{{ $pengiriman->wakafItem->wakif_name }}</td>
                                    <td>{{ $pengiriman->jenisQuran->nama_jenis }}</td>
                                    <td>
                                        <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $pengiriman->status->nama)) }}">
                                            {{ $pengiriman->status->nama }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($pengiriman->qr_code_path)
                                            ✅ Ada
                                        @else
                                            ❌ Tidak ada
                                        @endif
                                    </td>
                                    <td>{{ $pengiriman->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </form>
                
                @if($pengirimanList->hasPages())
                    <div class="pagination">
                        {{ $pengirimanList->links() }}
                    </div>
                @endif
            @else
                <div class="no-data">
                    <h3>🗂️ Tidak Ada Data</h3>
                    <p>Tidak ada pengiriman yang siap untuk di-print saat ini.</p>
                    <p>Pastikan pengiriman sudah memiliki QR code dan status "Proses Packing" atau lebih tinggi.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        let selectedCount = 0;

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.pengiriman-checkbox:checked');
            selectedCount = checkboxes.length;
            
            document.getElementById('selectedCount').textContent = selectedCount;
            document.getElementById('selectedText').textContent = `${selectedCount} pengiriman dipilih`;
            
            // Enable/disable buttons
            const previewBtn = document.getElementById('previewBtn');
            const printBtn = document.getElementById('printBtn');
            
            if (selectedCount > 0) {
                previewBtn.disabled = false;
                printBtn.disabled = false;
            } else {
                previewBtn.disabled = true;
                printBtn.disabled = true;
            }
            
            // Update check all checkbox
            const checkAll = document.getElementById('checkAll');
            const totalCheckboxes = document.querySelectorAll('.pengiriman-checkbox').length;
            
            if (selectedCount === 0) {
                checkAll.indeterminate = false;
                checkAll.checked = false;
            } else if (selectedCount === totalCheckboxes) {
                checkAll.indeterminate = false;
                checkAll.checked = true;
            } else {
                checkAll.indeterminate = true;
            }
        }

        function toggleAll() {
            const checkAll = document.getElementById('checkAll');
            const checkboxes = document.querySelectorAll('.pengiriman-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = checkAll.checked;
            });
            
            updateSelectedCount();
        }

        function selectAll() {
            document.querySelectorAll('.pengiriman-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        function selectNone() {
            document.querySelectorAll('.pengiriman-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function previewSelected() {
            if (selectedCount === 0) {
                alert('Pilih minimal satu pengiriman untuk preview');
                return;
            }
            
            // Get selected IDs
            const selectedIds = Array.from(document.querySelectorAll('.pengiriman-checkbox:checked'))
                .map(checkbox => checkbox.value);
            
            // Open preview in new window
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.thermal-print.bulk") }}';
            form.target = '_blank';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(csrfInput);
            
            // Add preview flag
            const previewInput = document.createElement('input');
            previewInput.type = 'hidden';
            previewInput.name = 'preview';
            previewInput.value = 'true';
            form.appendChild(previewInput);
            
            // Add selected IDs
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pengiriman_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function printSelected() {
            if (selectedCount === 0) {
                alert('Pilih minimal satu pengiriman untuk print');
                return;
            }
            
            if (!confirm(`Yakin ingin print ${selectedCount} label thermal?`)) {
                return;
            }
            
            // Get selected IDs
            const selectedIds = Array.from(document.querySelectorAll('.pengiriman-checkbox:checked'))
                .map(checkbox => checkbox.value);
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.thermal-print.bulk") }}';
            form.target = '_blank';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(csrfInput);
            
            // Add selected IDs
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pengiriman_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html>