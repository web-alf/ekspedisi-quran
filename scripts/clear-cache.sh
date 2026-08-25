#!/bin/bash

echo \"🔧 Clearing Laravel caches...\"

# Clear route cache
php artisan route:clear

# Clear config cache  
php artisan config:clear

# Clear view cache
php artisan view:clear

# Clear application cache
php artisan cache:clear

# Optimize for development
php artisan optimize:clear

echo \"✅ All caches cleared!\"
echo \"🚀 Now run: npm run dev\"
