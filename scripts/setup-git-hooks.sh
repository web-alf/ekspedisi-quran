#!/bin/bash

# =============================================================================
# Setup Git Hooks untuk Ekspedisi Quran
# =============================================================================

echo "🔧 Setting up Git Hooks..."

# Dapatkan direktori root project
PROJECT_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
cd "$PROJECT_ROOT"

# Copy hooks ke .git/hooks/
if [ -d ".githooks" ]; then
    echo "📋 Installing hooks..."

    for hook in .githooks/*; do
        if [ -f "$hook" ]; then
            hook_name=$(basename "$hook")
            cp "$hook" ".git/hooks/$hook_name"
            chmod +x ".git/hooks/$hook_name"
            echo "  ✅ $hook_name installed"
        fi
    done

    echo ""
    echo "🎉 Git hooks berhasil diinstall!"
    echo ""
    echo "📚 Hook yang tersedia:"
    echo "   • pre-commit - Validasi build & test sebelum commit"
    echo ""
else
    echo "❌ Directory .githooks tidak ditemukan"
    exit 1
fi
