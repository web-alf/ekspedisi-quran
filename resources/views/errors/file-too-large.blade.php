<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Terlalu Besar - Ekspedisi Quran</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-red-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-red-600 p-4 text-white">
            <h1 class="text-xl font-bold">⚠️ File Terlalu Besar</h1>
        </div>
        
        <div class="p-6">
            <div class="flex items-start mb-6">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Ukuran file melebihi batas maksimum</h2>
                    <p class="text-gray-600">
                        File yang Anda coba upload terlalu besar. Server tidak dapat memproses file dengan ukuran tersebut.
                    </p>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                <h3 class="font-semibold text-gray-800 mb-2">Batas ukuran file:</h3>
                <ul class="list-disc list-inside space-y-1 text-gray-600">
                    <li>Ukuran file maksimum (upload_max_filesize): <strong>{{ $maxFileSize }}</strong></li>
                    <li>Ukuran total upload maksimum (post_max_size): <strong>{{ $maxPostSize }}</strong></li>
                    <li>Batas ukuran untuk foto: <strong>2MB</strong></li>
                    <li>Batas ukuran untuk dokumen: <strong>5MB</strong></li>
                </ul>
            </div>
            
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-6">
                <h3 class="font-semibold text-yellow-800 mb-2">Tips untuk mengatasi masalah ini:</h3>
                <ul class="list-disc list-inside space-y-1 text-yellow-700">
                    <li>Kompres file gambar menggunakan layanan online seperti TinyPNG, CompressJPEG, dll.</li>
                    <li>Kurangi resolusi/dimensi gambar jika terlalu besar</li>
                    <li>Untuk file dokumen, simpan sebagai versi lebih ringan atau gunakan fitur "Compress PDF" jika tersedia</li>
                    <li>Pastikan tidak ada konten yang tidak perlu dalam file dokumen</li>
                </ul>
            </div>
            
            <div class="flex justify-between">
                <a href="javascript:history.back()" class="px-5 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700">
                    ← Kembali
                </a>
                <a href="/mushaf-request" class="px-5 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700">
                    Ke Formulir Permintaan
                </a>
            </div>
        </div>
    </div>
</body>
</html>
