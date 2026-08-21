# kuturogi ↔ kuturogi-admin 連携 API 設計

現行のポートフォリオ構成は **2アプリ・1データベース**（`SHARED_DATABASE=true`）です。Webhook / Integration API による二重書き込みは使いません。以下は別 DB で動かしていた時期の仕様です。

## 認証

| 方向 | 方式 | ヘッダ |
|------|------|--------|
| kuturogi-admin → kuturogi | API Key | `X-Integration-Api-Key` |
| kuturogi → kuturogi-admin | HMAC 署名 | `X-Kuturogi-Signature` |

署名の計算:

```
HMAC-SHA256(raw_request_body, INTEGRATION_WEBHOOK_SECRET)
```

---

## kuturogi → kuturogi-admin（Webhook）

### 予約確定

```
POST /api/webhooks/kuturogi/reservations
```

**Request body:**

```json
{
  "event": "reservation.created",
  "payload": {
    "id": 42,
    "user_id": 1,
    "room_id": 2,
    "plan_id": 3,
    "checkin_date": "2026-08-10",
    "checkout_date": "2026-08-12",
    "guest_count": 2,
    "room_count": 1,
    "adult_count": 2,
    "child_count": 0,
    "total_price": 80000,
    "status": "confirmed",
    "payment_method": "local",
    "guest_name": "山田 太郎",
    "guest_email": "taro@example.com"
  },
  "sent_at": "2026-08-06T10:00:00+09:00"
}
```

**Response:** `202 Accepted`

### 予約キャンセル

```
POST /api/webhooks/kuturogi/reservations/cancelled
```

**Payload:** `{ "id": 42 }`

### 会員登録

```
POST /api/webhooks/kuturogi/users
```

**Payload:** `{ "id": 1, "name": "...", "email": "..." }`

---

## kuturogi-admin → kuturogi（Integration API）

### マスタ取得（Phase 2）

```
GET /api/integration/rooms
GET /api/integration/plans
GET /api/integration/inventories?from=2026-08-01&to=2026-11-30&room_id=1
```

### 在庫更新（リアルタイムブロック）

```
PATCH /api/integration/inventories
X-Integration-Api-Key: {key}
```

**Request:**

```json
{
  "items": [
    { "room_id": 2, "date": "2026-08-10", "remains": 0 }
  ]
}
```

**Response:**

```json
{
  "status": "ok",
  "updated": [
    { "room_id": 2, "date": "2026-08-10", "remains": 0 }
  ]
}
```

### 客室・プラン更新

```
PATCH /api/integration/rooms/{id}
PATCH /api/integration/plans/{id}
```

### 予約一覧取得（同期用）

```
GET /api/integration/reservations?since=2026-08-01&status=confirmed
```

### 予約作成（社内 → kuturogi、在庫即ブロック）

```
POST /api/integration/reservations
```

### 予約キャンセル（在庫復元）

```
PATCH /api/integration/reservations/{id}/cancel
```

---

## Phase 2: 社内システム操作

### 初期同期

```bash
php artisan kuturogi:sync              # 全件
php artisan kuturogi:sync --rooms      # 客室のみ
php artisan kuturogi:sync --inventories --from=2026-08-01 --to=2026-12-31
```

### 管理画面

| 機能 | 場所 |
|------|------|
| ダッシュボード KPI | `/admin` |
| 在庫カレンダー | `/admin/inventory-calendar` |
| kuturogi 同期 | 客室一覧 → 「kuturogi から同期」 |
| 予約キャンセル | 予約一覧 → キャンセル（kuturogi 在庫復元） |
| 社内予約作成 | 予約一覧 → 新規（kuturogi へ即反映） |

---

## データフロー

### 予約が入った瞬間にブロック

```
[ゲスト] kuturogi 予約確定
    → ReservationController::store
    → room_inventories 減算（既存）
    → IntegrationWebhookDispatcher（新規）
    → kuturogi-admin Webhook 受信
    → ProcessReservationWebhook（Queue）
    → PMS 反映 + 売上計上
```

### 社内で在庫 0 に設定

```
[スタッフ] Filament 在庫編集
    → KuturogiSyncService::pushInventoryToKuturogi
    → PATCH /api/integration/inventories
    → kuturogi room_inventories 更新
    → 顧客サイト即「満室」
```

---

## 環境変数対応表

| kuturogi | kuturogi-admin | 用途 |
|----------|----------------|------|
| `INTEGRATION_API_KEY` | `KUTUROGI_API_KEY` | admin → kuturogi 認証 |
| `INTEGRATION_WEBHOOK_SECRET` | `KUTUROGI_INBOUND_WEBHOOK_SECRET` | kuturogi → admin 署名 |
| `INTEGRATION_WEBHOOK_URL` | — | Webhook 送信先（別コンテナ: `http://host.docker.internal:8081`） |
| — | `KUTUROGI_BASE_URL` | kuturogi API のベース URL（別コンテナ: `http://host.docker.internal:8080`） |

### 別 Dev Container で動かす場合

kuturogi と kuturogi-admin を **別コンテナ** で起動する場合、`localhost` では相互に届きません。ホストマシン経由で接続します。

| 設定 | 値 |
|------|-----|
| kuturogi-admin → kuturogi | `KUTUROGI_BASE_URL=http://host.docker.internal:8080` |
| kuturogi → kuturogi-admin | `INTEGRATION_WEBHOOK_URL=http://host.docker.internal:8081` |

両方のサーバーは **0.0.0.0** でバインドしてください:

```bash
# kuturogi コンテナ
php artisan serve --host=0.0.0.0 --port=8080

# kuturogi-admin コンテナ
php artisan serve --host=0.0.0.0 --port=8081
```

`.env` 変更後は両コンテナで `php artisan config:clear` を実行してください。

---

## Phase 3: CRM 強化

### 機能

| 機能 | 説明 |
|------|------|
| 会員 / ゲスト区分 | kuturogi 会員は `member`、予約のみは `guest` |
| 顧客詳細 | 累計利用額・予約回数・最終宿泊・タグ |
| 予約履歴 | 顧客詳細・編集画面から参照 |
| セグメント | リピーター（2回以上）、VIP（10万円以上）フィルター |
| 自動タグ | 予約同期時に `リピーター` `VIP` を自動付与 |
| 会員同期 | `GET /api/integration/users` + `php artisan kuturogi:sync --customers` |

### kuturogi API 追加

```
GET /api/integration/users
```

- Redis Pub/Sub によるイベントバス
- Laravel Reverb による管理画面リアルタイム更新
- 経理 CSV エクスポート、CRM 強化
