#!/bin/bash

# Install supervisor configuration for Ekspedisi Quran queue workers
# Run this script as root on production server

set -e

echo "🚀 Installing supervisor configuration for Ekspedisi Quran..."

# Install supervisor if not already installed
if ! command -v supervisord &> /dev/null; then
    echo "Installing supervisor..."
    apt-get update
    apt-get install -y supervisor
fi

# Create log directory
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage/logs

# Copy supervisor config
cp /var/www/html/deployment/queue-worker.conf /etc/supervisor/conf.d/ekspedisi-quran.conf

# Reload supervisor configuration
supervisorctl reread
supervisorctl update

# Start the programs
supervisorctl start ekspedisi-quran-queue:*
supervisorctl start ekspedisi-quran-scheduler:*

# Check status
supervisorctl status

echo "✅ Supervisor configuration installed successfully!"
echo "📋 To manage queue workers:"
echo "   - Check status: supervisorctl status"
echo "   - Restart workers: supervisorctl restart ekspedisi-quran-queue:*"
echo "   - View logs: tail -f /var/www/html/storage/logs/queue-worker.log"