<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    @include('errors.partials.error-style')
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <!-- Error Code -->
        <h1 class="text-9xl font-bold text-red-100 mb-4">500</h1>
        
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        
        <!-- Description -->
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Terjadi Kesalahan Server</h2>
        <p class="text-gray-600 mb-8 max-w-md mx-auto">
            Maaf, terjadi masalah teknis pada server. Tim kami sedang menanganinya.
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Muat Ulang
            </a>
        </div>
    </div>
</body>
</html>