# KUTUROGI管理システム

kuturogi 旅館の社内管理システムです。予約（PMS）、顧客（CRM）、客室・在庫、料金、売上を Filament の管理画面から運用します。顧客向けサイト kuturogi と **同じデータベース** を使い、予約番号や在庫が分かれないようにしています。

**閲覧者の方へ:** このリポジトリはポートフォリオ用の公開デモです。実在の個人情報は入力しないでください。セットアップ後、[http://localhost:8081/admin](http://localhost:8081/admin) で氏名を選び、パスワード `demo` で架空のデモユーザーとして入れます。

顧客向けサイト kuturogi とは **アプリは別、データベースは同一** です。予約番号・在庫・会員は1つの SQLite（または MySQL）を共有します。

## 機能

- **予約管理** — カレンダー / 一覧、社内予約の作成、キャンセル、部屋割当、チェックイン / チェックアウト。予約番号はゲストサイトと同じ `reservations.id` です
- **顧客管理** — 会員 / ゲスト区分、予約履歴、タグ、リピーター・VIP の自動付与
- **客室・プラン** — 客室タイプ、客室ユニット、プラン、設備・特徴。顧客サイトも同じテーブルを読む
- **在庫** — 在庫カレンダー。社内で在庫を 0 にすると顧客サイトが満室になる
- **料金設定** — シーズン料金、週末ルール、子供料金、キャンセル規定、オプション料金
- **売上・経理** — 予約確定時に自動計上
- **決済** — Stripe **テストモードのみ**（`pk_live_` / `sk_live_` は拒否）。テストカードは `4242 4242 4242 4242`
- **権限** — 管理者（編集可）と一般スタッフ（参照中心）
- **ダッシュボード** — 本日の CI/CO、確定予約数、今月売上、会員 / ゲスト / リピーター / VIP
- **デモログイン** — `DEMO_MODE=true` のとき、ログイン画面にパスワード案内を出す（既定: `demo`）。客室・プラン・料金・スタッフは削除不可。データは数時間ごとに初期化


## 技術スタック

| 項目 | 内容 |
|------|------|
| 言語 / ランタイム | PHP 8.3、Node.js 22 |
| フレームワーク | Laravel 13、Filament 3 |
| フロントエンド | Vite 7 |
| データベース | 開発: SQLite / 本番推奨: MySQL・MariaDB |
| 決済 | Stripe（テストモード） |
| 開発環境 | Dev Container（PHP 8.3 + MariaDB） |

## 必要環境

- PHP 8.3 以上（`intl` `pdo_mysql` `pdo_sqlite` `zip`）
- Composer
- Node.js 22 以上、npm
- SQLite、または MySQL / MariaDB

[VS Code](https://code.visualstudio.com/) / Cursor の Dev Containers を使う場合は、上記はコンテナ側に含まれます。

## セットアップ

シークレットは `.env` にだけ置きます。`.env` は git 管理しません。コピー元は `.env.example` です。

### Dev Container（推奨）

リポジトリを開いて Dev Container を起動すると、依存関係・マイグレーション・デモデータの投入まで実行されます。

```bash
./setup.sh                          # アセットビルドまで含める場合
php artisan serve --host=0.0.0.0 --port=8081
```

ブラウザで `/admin` を開き、氏名を選んでパスワード `demo` でログインしてください。会員登録は不要です。

デモデータはすべて架空です（例: 山田 太郎 / taro@example.com）。顧客は会員3割・ゲスト7割、リピーター3割、VIP1割になる人数（30名以上）で入り、稼働中客室の約8割が今日から1か月先まで埋まります。顧客・予約の作成画面にも注意書きが出ます。再投入は `php artisan db:seed` です。

### ローカル手動セットアップ

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

`php artisan db:seed`（`DemoSeeder`）は何度でも実行できます。デモ予約を作り直し、顧客比率（会員3割・リピーター3割・VIP1割）と今日から1か月先までの稼働率約8割を入れ直します。

`.env.example` の `DEMO_MODE=true` がログイン案内とダミー入力の注意書きを有効にします。Stripe を試す場合は **Test mode** の `pk_test_` / `sk_test_` だけを `.env` に書いてください。

公開デモでは `APP_DEBUG=false` にしてください。デモデータの定期初期化には cron が必要です。処理は **kuturogi-admin** 側で走ります（顧客サイトと同じ DB のため、こちらを初期化すれば予約サイト側も戻ります）。

```bash
* * * * * cd /path/to/kuturogi-admin && php artisan schedule:run >> /dev/null 2>&1
```

既定では 4 時間ごとに `php artisan demo:refresh`（`db:seed`）が走ります。間隔は `DEMO_REFRESH_HOURS` です。

### ロリポップへのアップロード（404 が出るとき）

Laravel は **公開ディレクトリが `public/`** です。ドメインの公開フォルダがプロジェクト直下だと `/admin` や Filament の CSS/JS が実ファイルとして見つからず、404 が連発します。

1. ユーザー専用ページ → **公開フォルダ設定** で、該当ドメインの公開フォルダを **`…/kuturogi-admin/public`** にする（パスは実際の配置に合わせる）
2. FileZilla などで **隠しファイルを表示**し、ルートの `.htaccess` と **`public/.htaccess` を必ずアップロードする**（先頭が `.` のファイルは初期設定で飛ばされやすい）
3. サーバー上で依存関係とアセットを用意する（またはローカルで作ってから上げる）

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan storage:link
chmod -R ug+rwx storage bootstrap/cache
```

4. `.env` の `APP_URL` を実際の HTTPS URL にする（`http://localhost:8081` のままだとアセットが別ホストを向く）。`APP_DEBUG=false`、`DEMO_MODE=true`
5. PHP は **8.3 以上**。公開フォルダを変えられない場合は、プロジェクト直下の `.htaccess` がリクエストを `public/` へ流します

`vendor/` と `public/build/` は git に含まれません。アップロード漏れでも画面が崩れたり 404 が出ます。

### ロリポップでの cron 設定

1. ユーザー専用ページ → **サーバーの管理・設定** → **タスクの追加（cron）**
2. 実行コマンドに、管理システムのルート（`artisan` があるディレクトリ）へ移動してから Laravel のスケジューラを叩く内容を入れる

```bash
cd /home/ユーザーID/web/kuturogi-admin && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

3. 実行タイミングは **1 分ごと**（Laravel が「4 時間ごと」かを判定します）

ロリポップで 1 分間隔が使えない場合は、スケジューラを使わず 4 時間ごとに直接初期化します。

```bash
cd /home/ユーザーID/web/kuturogi-admin && /usr/local/bin/php artisan demo:refresh >> /dev/null 2>&1
```

タイミングは `0 0,4,8,12,16,20 * * *`（毎日 0/4/8/12/16/20 時）など。PHP のパスはロリポップの「PHP 設定」で使っている版に合わせてください（例: `/usr/local/bin/php`、`/usr/local/bin/php-8.3`）。`.env` の `DEMO_MODE=true` と `DEMO_REFRESH_HOURS=4` を確認してください。

フロントエンドを変更する場合は、別ターミナルで `npm run dev` を起動してください。カスタム CSS / JS は `resources/css/`・`resources/js/` に置き、Blade から `@vite()` で読み込みます。

Webhook の非同期処理を使う場合は、さらに別ターミナルでキューワーカーを起動します。

```bash
php artisan queue:work
```

`Xdebug: Could not connect to debugging client` はデバッガ未接続時の通知です。無視して問題ありません。非表示にする場合は `export XDEBUG_MODE=off` を設定してください（Dev Container では設定済みです）。

## 管理画面

| 画面 | パス |
|------|------|
| ダッシュボード | `/admin` |
| 予約カレンダー | `/admin/reservations` |
| 予約一覧 | `/admin/reservations/list` |
| 在庫カレンダー | `/admin/inventory-calendar` |
| 顧客管理 | `/admin/customers` |
| 客室管理 | `/admin/room-units` |
| プラン | `/admin/plans` |
| 売上・経理 | `/admin/sales-records` |
| 料金設定 | `/admin/pricing-settings` |
| ユーザー | `/admin/users` |

ルート `/` は `/admin` へリダイレクトします。

## kuturogi との関係

2つの Laravel アプリ（顧客サイト / 管理画面）が **1つの DB** を共有します。

- スキーマのマイグレーションは **kuturogi-admin だけ** が実行する
- `users` は顧客サイトの会員、`staff_users` は管理画面のスタッフ
- 客室・プラン・在庫・予約は共通テーブル
- `.env` で `SHARED_DATABASE=true` と、kuturogi 側の `DB_DATABASE` を admin の SQLite パスにする

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

セッション Cookie 名を分けると、同じ `sessions` テーブルでもログインが混線しません。

`SHARED_DATABASE=true` のときは API / Webhook による二重書き込みは行いません。別 DB 時代の `php artisan kuturogi:sync` / `kuturogi:push` は no-op になります。

API 仕様の履歴は [INTEGRATION.md](./INTEGRATION.md) を参照してください。

## 環境変数

| 変数 | 説明 |
|------|------|
| `APP_URL` | 管理画面の URL（開発時は `http://localhost:8081`） |
| `DB_CONNECTION` | `sqlite`（開発）または `mysql` |
| `KUTUROGI_BASE_URL` | kuturogi Integration API のベース URL |
| `KUTUROGI_API_KEY` | admin → kuturogi 認証（kuturogi の `INTEGRATION_API_KEY` と同一） |
| `KUTUROGI_INBOUND_WEBHOOK_SECRET` | kuturogi → admin の HMAC 署名検証 |
| `KUTUROGI_WEBHOOK_SECRET` | admin → kuturogi 送信時の署名 |
| `SHARED_DATABASE` | `true` で顧客サイトと DB 共有（API 同期しない） |
| `DEMO_MODE` | `true` で注意書き・削除制限・定期初期化を有効化 |
| `DEMO_REFRESH_HOURS` | デモデータの初期化間隔（時間、既定 4） |
| `DEMO_LOGIN_PASSWORD` | 氏名選択ログイン用のデモパスワード（既定: `demo`） |
| `STRIPE_KEY` / `STRIPE_SECRET` | Stripe **テストモード**の公開鍵 / シークレット（`pk_test_` / `sk_test_` のみ） |
| `STRIPE_WEBHOOK_SECRET` | Stripe Webhook 署名 |

`.env` はリポジトリに含めません。公開ポートフォリオでは `DEMO_MODE=true` と `APP_DEBUG=false` にしてください。実際の旅館運用では `DEMO_MODE=false`、MySQL / MariaDB を使ってください。

## テスト

```bash
php artisan test
```

## プロジェクト構成

```
app/
├── Filament/
│   ├── Pages/              # 在庫カレンダー、料金設定
│   ├── Resources/          # 予約・顧客・客室・プラン・売上など
│   └── Widgets/            # ダッシュボード KPI
├── Http/Controllers/Api/   # kuturogi Webhook 受信
├── Jobs/                   # Webhook の非同期処理
├── Models/
└── Services/
    ├── KuturogiApiClient.php
    ├── KuturogiSyncService.php
    └── StripePaymentService.php
database/migrations/
routes/api.php
```

## ライセンス

MIT
