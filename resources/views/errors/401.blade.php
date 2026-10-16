<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 - Unauthorized</title>
    @include('errors.partials.error-style')
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <!-- Error Code -->
        <h1 class="text-9xl font-bold text-orange-100 mb-4">401</h1>
        
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-orange-50 rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
        </div>
        
        <!-- Description -->
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Login Diperlukan</h2>
        <p class="text-gray-600 mb-8 max-w-md mx-auto">
            Silakan login terlebih dahulu untuk mengakses halaman ini.
        </p>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="history.back()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Kembali
            </button>
            
            <a href="/login" class="inline-flex items-center px-6 py-3 text-white font-medium rounded-lg transition-colors" style="background-color: #eb3434; border: 1px solid #eb3434;" onmouseover="this.style.backgroundColor='#dc2626'; this.style.borderColor='#dc2626'" onmouseout="this.style.backgroundColor='#eb3434'; this.style.borderColor='#eb3434'">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Login
            </a>
        </div>
    </div>
</body>
</html>