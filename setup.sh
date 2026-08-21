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

if ! grep -q '^DEMO_MODE=' .env 2>/dev/null; then
    printf '\nDEMO_MODE=true\n' >> .env
fi

echo "==> Seeding demo data..."
php artisan db:seed --force

echo ""
echo "Setup complete."
echo ""
echo "Next steps:"
echo "  php artisan serve --port=8081    # 開発サーバー"
echo "  ブラウザで /admin → 氏名を選択し、パスワード demo でログイン"
echo "  npm run dev                      # Vite 開発サーバー（アセット変更時）"
echo "  php artisan queue:work           # Webhook 非同期処理（別ターミナル）"
