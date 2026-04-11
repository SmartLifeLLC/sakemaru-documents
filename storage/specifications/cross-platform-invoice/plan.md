# 酒丸帳場（documents）文書連携 作業計画

## 前提

### システム概要
- **対象**: sakemaru-documents（社内運用画面）
- **役割**: 修正/新規作成指示、公開制御、修正依頼参照、コメント投稿
- **ユーザー**: 社内スタッフ（company_user）

### 3プロジェクト協業仕様（base-spec.md）との関係
- このシステムの対応範囲: **§5.2**
- JSON仕様: **§6**（JSONの組み立てはこのシステムが担当）
- migration責任: **§8**（自システムDBのみmigration。システム間連携はDB直接アクセス）

### 他システムとの依存関係
```
酒丸本家（sakemaru）
  ├→ custom_invoice_queue（documents が同一DBへ直接投入する先）
  ├→ external_invoices（文書元データの取得元）
  └→ 初期値取得データ（修正フォームの初期表示用）

invoice（お得意先）
  ├→ document_change_requests（修正依頼一覧の参照元）
  ├→ document_change_comments（コメント投稿先 — invoice DBが正）
  └→ documents テーブル（公開フラグ操作の反映先）
```

### データフロー全体像
```
[1] 社内ユーザーが「修正」ボタン押下
      ↓
[2] 酒丸本家から文書元データ取得（初期値表示）
      ↓
[3] フォームで変更内容を入力
      ↓
[4] JSON v1 形式に組み立て
      ↓
[5] 酒丸本家 custom_invoice_queue に直接INSERT
      ↓
[6] 酒丸本家がqueueを処理 → PDF生成 → external_invoices登録
      ↓
[7] documents側で「公開」操作 → invoice側 documents テーブルに反映
      ↓
[8] 顧客（パートナー）が invoice 側で文書閲覧可能
```

### 連携方式（確定）
- **酒丸本家連携**: 同一DB直接連携（custom_invoice_queue / external_invoices）
- **invoice連携**: 別DB直接連携（document_change_requests / document_change_comments / documents）
- 本件のためのシステム間APIは作成しない

---

## Phase 一覧

| # | Phase | 概要 | 依存 | 完了条件 |
|---|-------|------|------|---------|
| P0 | 現状調査 | documentsプロジェクトの構造確認 | なし | 調査結果をboot.mdに記録 |
| P1 | 初期値取得DB連携 | 酒丸本家から文書元データ取得 | sakemaru P1 | 元データをフォームに初期表示 |
| P2 | 修正/新規作成フォーム | UI + JSON v1組み立て | sakemaru P7 | 3文書種別のフォーム動作 |
| P3 | custom_invoice_queue投入 | 酒丸本家DBへ直接INSERT | sakemaru P3 | queue INSERTと status 確認 |
| P4 | 修正依頼一覧/詳細 | invoice DBからの参照表示 | invoice P4 | 一覧・詳細・ステータス表示 |
| P5 | コメント投稿 | invoice DBへの直接書き込み | invoice P5 | コメント投稿 → invoice反映確認 |
| P6 | 公開フラグ制御 | 公開操作 → 顧客同期 | P3, invoice P2 | 公開後に顧客側で閲覧可能 |

---

## P0: 現状調査

### 目的
documentsプロジェクトの現在のコード構造・DB構成・認証方式を把握する。

### 調査手順

#### 1. プロジェクト構造確認
```bash
ls -la
cat composer.json | jq '.require'
cat .env.example
cat config/database.php  # 外部DB接続設定の有無
```

#### 2. 既存の文書管理機能確認
```bash
grep -rn "invoice\|document\|buyer" app/ --include="*.php" -l
grep -rn "closing\|bill" app/ --include="*.php" -l
```

#### 3. 外部DB連携設定の有無
```bash
grep -rn "sakemaru\|invoice" config/database.php .env.example
```

#### 4. 認証方式確認
```bash
grep -rn "auth\|guard\|middleware" routes/ --include="*.php"
```

### 完了条件
- [ ] プロジェクト構造を boot.md に記録
- [ ] 既存の文書関連機能の有無を把握
- [ ] 酒丸本家DB接続設定の現在値を確認
- [ ] invoice DB接続設定の現在値を確認

---

## P1: 初期値取得DB連携

### 目的
修正フォームの初期表示に必要な文書元データを酒丸本家から取得する。

### 実装方針

#### 取得元
- 酒丸本家 `external_invoices` テーブル（旧 `buyer_invoices`）
- uuid をキーにして取得
- metadata JSON に請求書鏡データ（salesman, branch, billing breakdown）が含まれる

#### 取得方式（確定）
- 酒丸本家DB（同一DB）から `external_invoices` を直接参照

#### 取得データ構造
```php
// 酒丸本家 external_invoices から取得する情報
$sourceData = [
    'uuid' => '...',
    'partner_id' => 10,
    'partner_code' => 'A001',
    'partner_name' => '株式会社サンプル',
    'closing_date' => '2026-02-28',
    'billing_amount' => 43700,
    'invoice_number' => 'INV-12345',
    'print_type' => 'INVOICE',
    'metadata' => [
        'branch' => ['code' => '...', 'name' => '...'],
        'salesman' => ['code' => '...', 'name' => '...'],
        'summary' => [
            'previous_balance_amount' => 50000,
            'deposit_total' => 10000,
            // ... 全項目
        ],
    ],
];
```

#### サービスクラス
```php
// app/Services/ExternalInvoiceClient.php
class ExternalInvoiceClient
{
    // 酒丸本家DBの直接参照
    public function getByUuid(string $uuid): ?array;
    public function getByClosingBillId(int $closingBillId): Collection;
}
```

### 完了条件
- [ ] uuid指定で元データ取得が動作
- [ ] metadata（請求書鏡データ）がフォームに初期表示可能
- [ ] 存在しないuuid時のエラーハンドリング

---

## P2: 修正/新規作成フォーム

### 目的
3種の文書（請求書・納品書・リベート請求書）の修正/新規作成フォームを提供する。

### フォーム構成

#### 共通項目
| 項目 | 型 | 必須 | 備考 |
|------|---|------|------|
| document_type | select | ○ | invoice / delivery_note / rebate_invoice |
| partner_id | select | ○ | 得意先/仕入れ先選択 |
| issue_date | date | ○ | 発行日 |
| closing_date | date | △ | 締め日（請求書時） |
| due_date | date | △ | 支払期日（請求書時） |
| title | text | ○ | 文書タイトル |
| remarks | textarea | × | 備考 |

#### 明細行（lines）
| 項目 | 型 | 必須 |
|------|---|------|
| line_no | auto | ○ |
| item_code | text | × |
| item_name | text | ○ |
| quantity | number | ○ |
| unit | text | × |
| unit_price | number | ○ |
| amount | computed | ○ |
| tax_rate | select (8/10) | ○ |
| tax_amount | computed | ○ |
| note | text | × |

#### 合計（totals） — 自動計算
- subtotal = Σ(lines.amount)
- tax_total = Σ(lines.tax_amount)
- grand_total = subtotal + tax_total

### JSON v1 組み立て（base-spec §6.2 準拠）
```php
// app/Services/CustomInvoiceJsonBuilder.php
class CustomInvoiceJsonBuilder
{
    public function build(string $commandType, array $formData): array
    {
        return [
            'schema_version' => '1.0',
            'command_type' => $commandType,  // 'create' or 'revise'
            'request_context' => [
                'request_uuid' => (string) Str::uuid(),
                'requested_at' => now()->toIso8601String(),
                'requested_by_system' => 'documents',
                'requested_by_user_id' => 'company_user:' . auth()->id(),
            ],
            'target' => [
                'client_id' => $formData['client_id'],
                'partner_id' => $formData['partner_id'],
                'document_type' => $formData['document_type'],
                'source_document_uuid' => $formData['source_uuid'] ?? null,
            ],
            'document' => [
                'document_number' => $formData['document_number'] ?? null,
                'issue_date' => $formData['issue_date'],
                'closing_date' => $formData['closing_date'] ?? null,
                'due_date' => $formData['due_date'] ?? null,
                'currency' => 'JPY',
                'title' => $formData['title'],
                'remarks' => $formData['remarks'] ?? '',
            ],
            'counterparty' => [
                'name' => $formData['partner_name'],
                'code' => $formData['partner_code'],
                'billing_email' => $formData['billing_email'] ?? null,
            ],
            'lines' => $this->buildLines($formData['lines']),
            'totals' => $this->buildTotals($formData['lines']),
            'attachments' => [],
            'publish_control' => [
                'requires_manual_publish' => true,
            ],
        ];
    }
}
```

### 修正時の初期値表示フロー
```
1. 「修正」ボタン押下 → source_uuid を取得
2. ExternalInvoiceClient::getByUuid(source_uuid) で元データ取得
3. metadata から各項目をフォームにマッピング
4. ユーザーが変更を入力
5. command_type = 'revise' + source_document_uuid 付きで JSON 組み立て
```

### 完了条件
- [ ] 3文書種別のフォームが表示される
- [ ] 修正時に元データが初期表示される
- [ ] 明細行の追加/削除/自動計算が動作
- [ ] JSON v1 形式への組み立てが正しい（base-spec §6.3 のルール全通過）

---

## P3: custom_invoice_queue 投入

### 目的
組み立てた JSON v1 を酒丸本家の `custom_invoice_queue` に直接INSERTする。

### 実装方針

#### DB接続設定
```php
// config/database.php
'sakemaru' => [
    'driver' => 'mysql',
    'host' => env('SAKEMARU_DB_HOST', '127.0.0.1'),
    'database' => env('SAKEMARU_DB_DATABASE', 'sakemaru'),
    'username' => env('SAKEMARU_DB_USERNAME'),
    'password' => env('SAKEMARU_DB_PASSWORD'),
],
```

#### 投入サービス
```php
// app/Services/CustomInvoiceQueueClient.php
class CustomInvoiceQueueClient
{
    /**
     * 酒丸本家 custom_invoice_queue に直接INSERT
     */
    public function enqueue(array $json, string $requestType, string $documentType): string
    {
        $queueUuid = (string) Str::uuid();

        DB::connection('sakemaru')->table('custom_invoice_queue')->insert([
            'queue_uuid' => $queueUuid,
            'request_type' => $requestType,
            'document_type' => $documentType,
            'target_client_id' => $json['target']['client_id'],
            'target_partner_id' => $json['target']['partner_id'] ?? null,
            'source_document_uuid' => $json['target']['source_document_uuid'] ?? null,
            'payload_json' => json_encode($json),
            'status' => 'queued',
            'requested_by_system' => 'documents',
            'requested_by_user_id' => $json['request_context']['requested_by_user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $queueUuid;
    }

    /**
     * queue の処理状況を確認
     */
    public function getStatus(string $queueUuid): ?object
    {
        return DB::connection('sakemaru')
            ->table('custom_invoice_queue')
            ->where('queue_uuid', $queueUuid)
            ->first();
    }
}
```

### 重複防止
- `queue_uuid` は UNIQUE 制約
- フォーム送信時に UUID を生成し、二重送信を防止
- 同一 `request_uuid` / `queue_uuid` の再送時はユニーク制約で重複を排除する

### ステータス監視
- 投入後、ユーザーに `queue_uuid` を返却
- ポーリング or WebSocket で処理状況を表示
  - `queued` → 「処理待ち」
  - `processing` → 「生成中」
  - `succeeded` → 「完了」（生成された文書へのリンク表示）
  - `failed` → 「エラー」（error_message表示）

### 完了条件
- [ ] フォーム送信 → custom_invoice_queue INSERT成功
- [ ] queue_uuid でステータス取得が動作
- [ ] 二重送信時にユニーク制約で重複排除される
- [ ] 酒丸本家側の queue:work が queued レコードを検出し処理を開始する

---

## P4: 修正依頼一覧/詳細

### 目的
顧客（パートナー）から invoice 側に投稿された修正依頼を、社内画面で参照する。

### 参照方式（確定）
- invoice DB（別DB接続）を直接参照

### 画面構成
1. **修正依頼一覧**
   - フィルタ: ステータス、パートナー名、文書種別、日付範囲
   - 列: 依頼ID、文書名、パートナー名、依頼種別、ステータス、依頼日
   - ソート: 依頼日降順（新しい順）

2. **修正依頼詳細**
   - 依頼内容（種別 + 説明文 + 変更希望JSON）
   - コメント履歴（時系列）
   - ステータス変更ボタン（in_review → resolved / rejected）
   - コメント投稿フォーム

### ステータス遷移（社内側が操作）
```
open → in_review → resolved     （対応完了）
                 → rejected     （却下）
open → canceled                 （依頼者がキャンセル — invoice側操作）
```

### 完了条件
- [ ] 修正依頼一覧のフィルタ・ソート・ページネーション動作
- [ ] 詳細画面でコメント履歴が時系列表示
- [ ] ステータス変更が invoice DB に反映

---

## P5: コメント投稿

### 目的
修正依頼に対するコメントを invoice DB に投稿する。

### 投稿方式

#### invoice DB 直接書き込み（base-spec §4.2 — コメント保存先は invoice DB）
```php
// app/Services/DocumentChangeCommentClient.php
class DocumentChangeCommentClient
{
    /**
     * invoice DB にコメント投稿
     */
    public function post(int $requestId, string $body): bool
    {
        return DB::connection('invoice')
            ->table('document_change_comments')
            ->insert([
                'request_id' => $requestId,
                'commenter_type' => 'company_user',
                'commenter_id' => auth()->id(),
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

}
```

### 接続方式（要確定）
- `invoice` DB接続を利用
- 書き込み専用資格情報で最小権限を付与

### 完了条件
- [ ] コメント投稿 → invoice DB に反映
- [ ] commenter_type = 'company_user' で記録される
- [ ] 投稿後にコメント履歴に即座に表示

---

## P6: 公開フラグ制御

### 目的
修正/新規作成した文書を「公開」操作することで、顧客（invoice側）から閲覧可能にする。

### 公開フロー
```
1. custom_invoice_queue の status = 'succeeded' を確認
2. 生成された文書を documents 画面でプレビュー
3. 社内ユーザーが「公開」ボタン押下
4. invoice DBの `documents` テーブルにレコードを作成/更新して同期
5. 顧客が invoice 側で閲覧可能
```

### 実装方針

#### 公開操作
```php
// invoice DBの documents テーブルへ同期（作成/更新）
```

#### 同期対象データ
- uuid, document_type, file_type
- S3パス情報（bucket, key, file_name）
- partner_id, partner_code, partner_name
- billing_amount, metadata
- status = 'synced'

### 公開制御ルール
- `requires_manual_publish = true` の文書は手動公開必須（base-spec §6.2）
- 公開前のプレビュー機能を提供
- 公開取り消し（unpublish）機能（invoice側のレコードを非公開状態に）

### 完了条件
- [ ] 「公開」操作で invoice 側に文書が反映
- [ ] 公開前のプレビューが動作
- [ ] 公開済み文書が顧客側（invoice）で閲覧可能
- [ ] 公開取り消しが動作

---

## 制約（厳守）

1. **文書生成はこのシステムでは行わない** — 酒丸本家の責務（base-spec §3.1.1）
2. **コメント保存先は invoice DB** — 別DB直接書き込み（base-spec §4.2.5）
3. **JSON v1 必須準拠** — schema_version, command_type, 必須項目（base-spec §6.3）
4. **custom_invoice_queue の migration はしない** — 酒丸本家チームが管理（base-spec §8）
5. **migrate:fresh/refresh 禁止** — 本番データ保護
6. **二重送信防止** — queue_uuid ユニーク制約で担保

## 全体完了条件

- [ ] 修正フォーム: 元データ取得 → 編集 → JSON組み立て → queue投入 が一貫動作
- [ ] 新規作成フォーム: 入力 → JSON組み立て → queue投入 が一貫動作
- [ ] 3文書種別（invoice, delivery_note, rebate_invoice）のフォーム動作
- [ ] 修正依頼一覧/詳細 → コメント投稿 → invoice DB反映
- [ ] 公開操作 → 顧客側で閲覧可能
- [ ] queue投入後のステータス監視が動作
