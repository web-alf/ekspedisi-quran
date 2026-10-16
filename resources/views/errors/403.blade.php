<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    @include('errors.partials.error-style')
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <!-- Error Code -->
        <h1 class="text-9xl font-bold text-red-100 mb-4">403</h1>
        
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
        </div>
        
        <!-- Description -->
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Akses Ditolak</h2>
        <p class="text-gray-600 mb-8 max-w-md mx-auto">
            Anda tidak memiliki izin untuk mengakses halaman ini.
        </p>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="history.back()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Kembali
            </button>
            
            <a href="/" class="inline-flex items-center px-6 py-3 text-white font-medium rounded-lg transition-colors" style="background-color: #eb3434; border: 1px solid #eb3434;" onmouseover="this.style.backgroundColor='#dc2626'; this.style.borderColor='#dc2626'" onmouseout="this.style.backgroundColor='#eb3434'; this.style.borderColor='#eb3434'">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>