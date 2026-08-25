<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Tidak Ditemukan - Ekspedisi Quran</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-yellow-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-yellow-600 p-4 text-white">
            <h1 class="text-xl font-bold">QR Code Tidak Ditemukan</h1>
        </div>
        
        <div class="p-6">
            <div class="flex items-start mb-6">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                    <svg class="w-7 h-7 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">QR Code untuk pengiriman ini tidak tersedia</h2>
                    <p class="text-gray-600">
                        QR Code untuk pengiriman ini belum dibuat atau file telah hilang dari sistem.
                    </p>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                <h3 class="font-semibold text-gray-800 mb-2">Langkah yang dapat dilakukan:</h3>
                <ul class="list-disc list-inside space-y-1 text-gray-600">
                    <li>Hubungi administrator untuk membuat QR Code baru</li>
                    <li>Periksa kembali nomor resi atau ID pengiriman</li>
                    <li>Pastikan pengiriman sudah diproses oleh sistem</li>
                    <li>Coba scan QR Code lain untuk memastikan scanner berfungsi</li>
                </ul>
            </div>
            
            <div class="flex justify-between">
                <a href="javascript:history.back()" class="px-5 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700">
                    ← Kembali
                </a>
                <a href="/admin/dashboard" class="px-5 py-2 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700">
                    Ke Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
