# KUTUROGI管理システム

旅館向けの社内管理画面です。予約（PMS）、顧客（CRM）、客室・在庫、料金、売上、お知らせを [Filament](https://filamentphp.com/) から運用します。

顧客向けサイト kuturogi とはアプリを分け、**データベースは共有**します。予約番号・在庫・会員が二重管理にならない構成です。

このリポジトリはポートフォリオ用の公開デモです。実在の個人情報は入力しないでください。ソースの閲覧は構いませんが、無断での利用・複製はしないでください。セットアップ後、ユーザーを選び、パスワード `demo` で入れます。会員登録は不要です。

## 機能

- **予約** — カレンダー / 一覧、社内予約、キャンセル、部屋割当、チェックイン / アウト。予約番号は顧客サイトと同じ `reservations.id`
- **顧客** — 会員 / ゲスト、予約履歴、タグ、リピーター・VIP の自動付与
- **客室・プラン** — 客室タイプ、客室ユニット、プラン、設備。顧客サイトも同じテーブルを参照
- **在庫** — 在庫カレンダー。社内で残室を 0 にすると顧客サイトが満室になる
- **料金** — シーズン、週末、子供料金、キャンセル規定、オプション
- **お知らせ** — 顧客サイトのトップとニュース一覧へ掲載する投稿を管理（共有テーブル `news`）
- **売上** — 予約確定時に自動計上
- **決済** — Stripe テストモードのみ（`pk_live_` / `sk_live_` は拒否）。テストカードは `4242 4242 4242 4242`
- **権限** — 管理者（編集可）と一般スタッフ（参照中心）
- **ダッシュボード** — 本日の CI/CO、確定予約、今月売上、会員 / ゲスト / リピーター / VIP
- **デモモード** — ログイン案内、マスタ削除の禁止、数時間ごとのデータ初期化（`DEMO_MODE=true`）

## 技術スタック

| 項目 | 内容 |
|------|------|
| 言語 | PHP 8.3、Node.js 22 |
| フレームワーク | Laravel 13、Filament 3 |
| フロントエンド | Vite 7 |
| データベース | 開発: SQLite / 本番推奨: MySQL・MariaDB |
| 決済 | Stripe（テストモード） |
| 開発環境 | Dev Container（PHP 8.3 + MariaDB） |

## 必要環境

- PHP 8.3 以上（`intl` `pdo_mysql` `pdo_sqlite` `zip`）
- Composer、Node.js 22 以上、npm
- SQLite、または MySQL / MariaDB

VS Code / Cursor の Dev Containers を使う場合、上記はコンテナに含まれます。

## セットアップ

シークレットは `.env` にだけ置きます（git 管理しません）。雛形は `.env.example` です。

### Dev Container（推奨）

リポジトリを開いてコンテナを起動したあと:

```bash
./setup.sh
php artisan serve --host=0.0.0.0 --port=8081
```

`/admin` を開き、氏名を選んでパスワード `demo` でログインします。

デモデータはすべて架空です。顧客は会員約3割・ゲスト約7割、リピーター約3割、VIP約1割（30名以上）。稼働中客室の約8割が、今日から1か月先まで埋まります。作り直しは `php artisan db:seed` です。

### ローカル

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan serve --port=8081
```

フロントを触るときは `npm run dev`、Webhook のキューを使うときは `php artisan queue:work` を別ターミナルで起動します。

Stripe を試す場合は Test mode の `pk_test_` / `sk_test_` だけを `.env` に書いてください。公開デモでは `APP_DEBUG=false`、`DEMO_MODE=true` にします。

## 管理画面

| 画面 | パス |
|------|------|
| ダッシュボード | `/admin` |
| 予約カレンダー | `/admin/reservations` |
| 予約一覧 | `/admin/reservations/list` |
| 在庫カレンダー | `/admin/inventory-calendar` |
| 顧客 | `/admin/customers` |
| 客室ユニット | `/admin/room-units` |
| プラン | `/admin/plans` |
| お知らせ | `/admin/news` |
| 売上 | `/admin/sales-records` |
| 料金設定 | `/admin/pricing-settings` |
| ユーザー | `/admin/users` |

`/` は `/admin` へリダイレクトします。

## 顧客サイトとの関係

2つの Laravel アプリが 1つの DB を共有します。

- マイグレーションは **kuturogi-admin だけ** が実行する
- `users` は顧客サイトの会員、`staff_users` は管理画面のスタッフ
- 客室・プラン・在庫・予約・お知らせは共通テーブル
- セッション Cookie 名を分ける（同じ `sessions` テーブルでもログインが混線しない）

**kuturogi-admin**

```env
SHARED_DATABASE=true
SESSION_COOKIE=kuturogi_admin_session
```

**kuturogi**

```env
SHARED_DATABASE=true
SESSION_COOKIE=kuturogi_session
DB_DATABASE=/workspaces/kuturogi-admin/database/database.sqlite
```

`SHARED_DATABASE=true` のときは API / Webhook による二重書き込みはしません。別 DB 時代の仕様は [INTEGRATION.md](./INTEGRATION.md) に残しています。


```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan storage:link
chmod -R ug+rwx storage bootstrap/cache
```

```bash
cd /home/ユーザーID/web/kuturogi-admin && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 環境変数

| 変数 | 説明 |
|------|------|
| `APP_URL` | 管理画面の URL（開発時は `http://localhost:8081`） |
| `DB_CONNECTION` | `sqlite` または `mysql` |
| `SHARED_DATABASE` | `true` で顧客サイトと DB 共有 |
| `DEMO_MODE` | 注意書き・削除制限・定期初期化 |
| `DEMO_REFRESH_HOURS` | 初期化間隔（時間、既定 4） |
| `DEMO_LOGIN_PASSWORD` | デモ用パスワード（既定 `demo`） |
| `KUTUROGI_BASE_URL` | 顧客サイト Integration API のベース URL |
| `KUTUROGI_API_KEY` | admin → 顧客サイトの認証 |
| `KUTUROGI_WEBHOOK_SECRET` | admin → 顧客サイト送信時の署名 |
| `KUTUROGI_INBOUND_WEBHOOK_SECRET` | 顧客サイト → admin の HMAC 検証 |
| `STRIPE_KEY` / `STRIPE_SECRET` | テストモードの鍵のみ（`pk_test_` / `sk_test_`） |
| `STRIPE_WEBHOOK_SECRET` | Stripe Webhook 署名 |


## テスト

```bash
php artisan test
```

## 構成

```
app/
├── Filament/          管理画面（Resources / Pages / Widgets）
├── Http/Controllers/  Webhook 受信
├── Jobs/              Webhook の非同期処理
├── Models/
└── Services/          在庫・決済・顧客サイト連携
database/migrations/
routes/
```

## ライセンス

無断利用を禁止します。ソースの閲覧のみ許可します。詳細は [LICENSE](./LICENSE) を参照してください。
