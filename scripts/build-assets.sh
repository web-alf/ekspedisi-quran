#!/bin/bash

# ==============================================
# Ekspedisi Quran - Local Build Script
# ==============================================

echo "🔨 Building Ekspedisi Quran assets..."

# Check if we're in the right directory
if [ ! -f "package.json" ]; then
    echo "❌ Error: package.json not found. Are you in the project root?"
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
fi

# Clean previous build
echo "🧹 Cleaning previous build..."
rm -rf public/build/*

# Build assets
echo "🏗️ Building production assets with Vite..."
npm run build

# Check if build was successful
if [ -f "public/build/manifest.json" ]; then
    echo "✅ Build completed successfully!"
    echo "📄 Manifest file created: public/build/manifest.json"
    
    # Show manifest contents
    echo ""
    echo "📋 Build manifest:"
    cat public/build/manifest.json | jq '.' 2>/dev/null || cat public/build/manifest.json
    
    # List built assets
    echo ""
    echo "📁 Built assets:"
    ls -la public/build/assets/ 2>/dev/null || echo "No assets directory found"
    
else
    echo "❌ Build failed! manifest.json not found."
    exit 1
fi

echo ""
echo "🎉 Ready for production deployment!"
echo ""
echo "Next steps:"
echo "1. Copy files to production server"
echo "2. Run deployment script on server"
echo "3. Update .env with production settings"
