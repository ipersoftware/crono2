#!/bin/bash

# Script di deploy per Ermes
echo "🚀 Inizio deploy..."

# Git pull
echo "📥 Git pull..."
git pull

# Composer install (se necessario)
# composer install --no-dev --optimize-autoloader

# NPM install e build
echo "📦 NPM build..."
npm ci --prefer-offline
npm run build

# Pulizia cache Laravel
echo "🧹 Pulizia cache..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Ottimizzazione (opzionale per produzione)
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

echo "✅ Deploy completato!"
