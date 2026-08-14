#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

echo "==> Installing PHP dependencies..."
composer install --no-interaction

echo "==> Installing Node dependencies and building Vite assets..."
npm install
npm run build

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

echo "==> Running migrations..."
php artisan migrate --force

echo ""
echo "Setup complete."
echo ""
echo "Next steps:"
echo "  php artisan make:filament-user   # 管理ユーザー作成（初回のみ）"
echo "  php artisan serve --port=8081    # 開発サーバー"
echo "  npm run dev                      # Vite 開発サーバー（アセット変更時）"
echo "  php artisan queue:work           # Webhook 非同期処理（別ターミナル）"
