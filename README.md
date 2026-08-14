# Kuturogi Admin

kuturogi 旅館の社内管理システム（PMS / CRM / 在庫・料金 / 経理 / ダッシュボード）。

## 技術スタック

- Laravel 13
- Filament 3
- Vite（フロントエンドアセット）
- SQLite（開発）/ MySQL（本番推奨）

## セットアップ

> **重要:** 初回は必ず `composer install` を実行してください。`vendor/` がないと `php artisan` は動きません。

```bash
cd /workspaces/kuturogi-admin   # Dev Container 内

# 一括セットアップ（推奨）
./setup.sh

# または手動
composer install
npm install && npm run build
cp .env.example .env   # 未作成の場合
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan make:filament-user
```

フロントエンド開発時は別ターミナルで `npm run dev` を起動してください。カスタム CSS/JS は `resources/css/`・`resources/js/` で管理し、Blade からは `@vite()` で読み込みます。

Dev Container を **Rebuild** すると `postCreateCommand` で `setup.sh` が自動実行されます。

### Xdebug の警告について

`Xdebug: Could not connect to debugging client` は **無視して問題ありません**（デバッガ未接続時の通知）。非表示にする場合:

```bash
export XDEBUG_MODE=off
```

（`.devcontainer/devcontainer.json` に `XDEBUG_MODE=off` を設定済み。Rebuild 後は出なくなります。）

## kuturogi との連携設定

`.env` に以下を設定してください（kuturogi 側と同じ API Key / Secret を使用）。

```env
KUTUROGI_BASE_URL=http://localhost:8080
KUTUROGI_API_KEY=your-shared-api-key
KUTUROGI_WEBHOOK_SECRET=your-shared-webhook-secret
KUTUROGI_INBOUND_WEBHOOK_SECRET=your-shared-webhook-secret
```

kuturogi 側（`.env`）:

```env
INTEGRATION_API_KEY=your-shared-api-key
INTEGRATION_WEBHOOK_URL=http://localhost:8081
INTEGRATION_WEBHOOK_SECRET=your-shared-webhook-secret
```

詳細は [INTEGRATION.md](./INTEGRATION.md) を参照。

## 管理画面

- URL: http://localhost:8081/admin
- 機能（Phase 1 ひな型）:
  - **予約管理** — kuturogi からの Webhook 取り込み
  - **顧客管理** — 会員・ゲスト情報
  - **在庫管理** — 変更を kuturogi へ即時反映
  - **売上・経理** — 予約確定時に自動計上

## プロジェクト構成

```
app/
├── Filament/Resources/     # 管理画面
├── Services/
│   ├── KuturogiApiClient.php    # kuturogi API 呼び出し
│   └── KuturogiSyncService.php  # 双方向同期ロジック
├── Http/Controllers/Api/
│   └── KuturogiWebhookController.php  # Webhook 受信
└── Jobs/
    └── ProcessReservationWebhook.php  # 非同期処理
```

## 次の Phase

- **Phase 2** ✅ PMS、在庫カレンダー、双方向同期、`kuturogi:sync`
- **Phase 3** 🔄 CRM 強化（完了）→ 経理 CSV エクスポート、リアルタイム UI（Reverb）
