<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Layanan Tidak Tersedia</title>
    @include('errors.partials.error-style')
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <!-- Error Code -->
        <h1 class="text-9xl font-bold text-purple-100 mb-4">503</h1>
        
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-purple-50 rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
            </div>
        </div>
        
        <!-- Description -->
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Layanan Tidak Tersedia</h2>
        <p class="text-gray-600 mb-8 max-w-md mx-auto">
            Sistem sedang dalam pemeliharaan. Silakan coba lagi dalam beberapa saat.
        </p>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="history.back()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Kembali
            </button>
            
            <button onclick="location.reload()" class="inline-flex items-center px-6 py-3 text-white font-medium rounded-lg transition-colors" style="background-color: #eb3434; border: 1px solid #eb3434;" onmouseover="this.style.backgroundColor='#dc2626'; this.style.borderColor='#dc2626'" onmouseout="this.style.backgroundColor='#eb3434'; this.style.borderColor='#eb3434'">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Muat Ulang
            </button>
        </div>
    </div>
</body>
</html>