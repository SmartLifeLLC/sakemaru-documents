# チャット機能（問い合わせ管理）実装仕様書

sakemaru-documents（管理側）から sakemaru-partner-invoice の `document_change_requests` / `document_change_comments` テーブルにアクセスし、チャットUIを実装するための仕様書。

---

## 1. DBスキーマ

### document_change_requests

| カラム | 型 | 備考 |
|--------|------|------|
| id | bigint (PK) | |
| request_uuid | uuid (unique) | |
| document_id | unsignedBigInteger | documents.id |
| client_id | unsignedBigInteger (nullable) | clients.id — テナンシーフィルタリング用 |
| requester_partner_id | unsignedBigInteger | partners.id |
| status | varchar(20) | default: 'open' |
| request_type | varchar(50) | name_change / address_change / amount_error / remark_addition / other |
| description | text | |
| requested_payload_json | json (nullable) | |
| resolved_at | timestamp (nullable) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**インデックス:**
- `document_change_requests_document_idx` (document_id)
- `document_change_requests_client_idx` (client_id)
- `document_change_requests_requester_idx` (requester_partner_id)
- `document_change_requests_status_idx` (status)

### document_change_comments

| カラム | 型 | 備考 |
|--------|------|------|
| id | bigint (PK) | |
| request_id | unsignedBigInteger | document_change_requests.id |
| commenter_type | varchar(20) | partner_user / company_user / system |
| commenter_id | unsignedBigInteger | commenter_type に応じた FK |
| body | text | |
| attachments | json (nullable) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**インデックス:**
- `document_change_comments_request_idx` (request_id)
- `document_change_comments_commenter_idx` (commenter_type, commenter_id)

---

## 2. commenter_type の使い分け

| commenter_type | 意味 | 使用側 |
|---------------|------|--------|
| `partner_user` | 顧客側（パートナーユーザー） | sakemaru-partner-invoice |
| `company_user` | 管理側（社内ユーザー） | sakemaru-documents |
| `system` | システム自動メッセージ | どちらからでも |

- `commenter_id` は、`partner_user` の場合は `partner_users.id`、`company_user` の場合は管理側のユーザーID
- sakemaru-documents 側でコメント作成時は `commenter_type = 'company_user'` を使用する

---

## 3. クエリパターン

### 3-1. クライアントの問い合わせ一覧取得

```php
// invoice_read 接続を使用
$requests = DB::connection('invoice_read')
    ->table('document_change_requests')
    ->where('client_id', $clientId)
    ->orderByDesc('updated_at')
    ->get();
```

### 3-2. 問い合わせ詳細 + コメント取得

```php
$request = DB::connection('invoice_read')
    ->table('document_change_requests')
    ->where('id', $requestId)
    ->where('client_id', $clientId) // テナンシーガード
    ->first();

$comments = DB::connection('invoice_read')
    ->table('document_change_comments')
    ->where('request_id', $requestId)
    ->orderBy('created_at')
    ->orderBy('id')
    ->get();
```

### 3-3. 管理側からのコメント書き込み

```php
DB::connection('invoice_write')->table('document_change_comments')->insert([
    'request_id'     => $requestId,
    'commenter_type' => 'company_user',
    'commenter_id'   => $companyUserId,
    'body'           => $body,
    'attachments'    => null,
    'created_at'     => now(),
    'updated_at'     => now(),
]);
```

### 3-4. ステータス更新

```php
DB::connection('invoice_write')
    ->table('document_change_requests')
    ->where('id', $requestId)
    ->where('client_id', $clientId) // テナンシーガード
    ->update([
        'status'      => $newStatus,
        'resolved_at' => in_array($newStatus, ['resolved', 'rejected']) ? now() : null,
        'updated_at'  => now(),
    ]);
```

---

## 4. ステータスワークフロー

```
open → in_review → resolved
                 → rejected
open → canceled (顧客側がキャンセル)
```

| ステータス | 意味 | 遷移元 | 操作者 |
|-----------|------|--------|--------|
| `open` | 受付（新規・顧客が追加コメント時にもリセット） | — / in_review | 顧客側 |
| `in_review` | 確認中 | open | 管理側 |
| `resolved` | 完了 | in_review | 管理側 |
| `rejected` | 却下 | in_review / open | 管理側 |
| `canceled` | 取消 | open | 顧客側 |

管理側で主に操作するステータス遷移:
- `open` → `in_review`: 問い合わせを確認開始
- `in_review` → `resolved`: 対応完了
- `in_review` → `rejected`: 対応不可・却下
- `open` → `rejected`: 不正な依頼を即却下

---

## 5. 未読判定ロジック

### 管理側から見た未読

「顧客（partner_user）の最新コメント日時 > 管理側（company_user）の最新コメント日時」であれば未読。

```php
$unreadRequestIds = DB::connection('invoice_read')
    ->table('document_change_comments')
    ->selectRaw("
        request_id,
        MAX(CASE WHEN commenter_type = 'company_user' THEN created_at END) AS last_company_at,
        MAX(CASE WHEN commenter_type = 'partner_user' THEN created_at END) AS last_partner_at
    ")
    ->whereIn('request_id', $requestIds)
    ->groupBy('request_id')
    ->get()
    ->filter(function ($row) {
        // 管理側がまだ返信していない、または顧客の最新が管理側より新しい
        if (! $row->last_partner_at) {
            return false;
        }
        return ! $row->last_company_at || $row->last_partner_at > $row->last_company_at;
    })
    ->pluck('request_id')
    ->all();
```

### 顧客側から見た未読（参考：partner-invoice で実装済み）

逆パターン — `company_user` の最新 > `partner_user` の最新。

---

## 6. チャットUI仕様

### 6-1. レイアウト

```
┌────────────────────────────────────────────────────┐
│  問い合わせ管理                                      │
├──────────────┬─────────────────────────────────────┤
│ リスト        │ チャット詳細                          │
│              │                                     │
│ [●] INV-001 │  ┌─────────────────────────────┐    │
│   金額誤り   │  │ 顧客: 日高屋東京 経理A       │    │
│   2026-02-25 │  │ 請求金額が異なります          │    │
│              │  └─────────────────────────────┘    │
│  INV-002     │           ┌────────────────────┐    │
│   宛名変更   │           │ 社内: 担当者B       │    │
│   2026-02-24 │           │ 確認します          │    │
│              │           └────────────────────┘    │
│              │                                     │
│              │  ┌──────────────────────┬──────┐    │
│              │  │ メッセージを入力...    │ 送信 │    │
│              │  └──────────────────────┴──────┘    │
│              │                                     │
│              │  ステータス: [確認中 ▼]              │
├──────────────┴─────────────────────────────────────┤
│                                                    │
└────────────────────────────────────────────────────┘
```

### 6-2. バブル表示

- **顧客側コメント** (`partner_user`): 左寄せ、薄い灰色背景
- **管理側コメント** (`company_user`): 右寄せ、青系背景
- **システムメッセージ** (`system`): 中央寄せ、小文字

各バブルに表示する情報:
- コメント者名（`commenter_type` + `commenter_id` から解決）
- 本文 (`body`)
- 投稿日時 (`created_at`)

### 6-3. 送信フォーム

- テキストエリア（最大5000文字）
- 送信ボタン
- 送信時に `commenter_type = 'company_user'` で `document_change_comments` に INSERT

### 6-4. ステータス変更

- チャット詳細画面にステータス変更ドロップダウンを配置
- 変更時に `document_change_requests.status` を UPDATE
- `resolved` / `rejected` 選択時は `resolved_at` を `now()` に設定

---

## 7. 接続設定

sakemaru-documents の `config/database.php` に以下の接続を設定（または既存の接続を利用）:

| 接続名 | 用途 | 権限 |
|--------|------|------|
| `invoice_read` | 問い合わせ一覧・詳細・コメント取得 | SELECT のみ |
| `invoice_write` | コメント書き込み・ステータス更新 | SELECT / INSERT / UPDATE |

両接続とも sakemaru-partner-invoice の DB（`document_change_requests` / `document_change_comments` テーブル）を参照する。

```php
// config/database.php
'invoice_read' => [
    'driver'   => 'mysql',
    'host'     => env('INVOICE_DB_READ_HOST'),
    'database' => env('INVOICE_DB_DATABASE'),
    'username' => env('INVOICE_DB_READ_USERNAME'),
    'password' => env('INVOICE_DB_READ_PASSWORD'),
    // ...
],
'invoice_write' => [
    'driver'   => 'mysql',
    'host'     => env('INVOICE_DB_WRITE_HOST'),
    'database' => env('INVOICE_DB_DATABASE'),
    'username' => env('INVOICE_DB_WRITE_USERNAME'),
    'password' => env('INVOICE_DB_WRITE_PASSWORD'),
    // ...
],
```

---

## 実装チェックリスト

- [ ] `invoice_read` / `invoice_write` 接続設定を追加
- [ ] 問い合わせ一覧ページ（client_id フィルタリング）
- [ ] 問い合わせ詳細 + チャットUI
- [ ] コメント送信機能（company_user として書き込み）
- [ ] ステータス変更機能
- [ ] 未読バッジ表示
- [ ] コメント者名の解決（partner_users / company_users テーブルから名前取得）
/
