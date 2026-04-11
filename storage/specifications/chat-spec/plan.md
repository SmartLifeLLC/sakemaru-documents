# チャット機能（問い合わせ管理）作業計画

## 前提

- 修正依頼一覧（`DocumentChangeRequestsResource`）は既に実装済み
  - 一覧テーブル（ステータスバッジ、日付フィルタ、取引先検索）
  - モーダルアクション: 詳細表示（plaintext）、ステータス変更、コメント投稿
- モデル: `DocumentChangeRequest`, `DocumentChangeComment` 実装済み
- サービス: `DocumentChangeRequestClient`, `DocumentChangeCommentClient` 実装済み
- DB接続: `invoice_read` / `invoice_write` 設定済み

**目標**: モーダル表示のplaintextチャットを、専用ページの本格的なチャットUIに拡張する。

---

## Phase 一覧

| # | Phase | 概要 | 完了条件 |
|---|-------|------|---------|
| P1 | チャット詳細ページ作成 | Filament View Pageを追加し、一覧からクリックで遷移可能にする | 詳細ページにアクセスでき、問い合わせ情報が表示される |
| P2 | チャットUI実装 | Blade + Livewire でバブル形式チャットUIを実装 | コメントがバブル形式（左:顧客/右:管理側）で表示される |
| P3 | コメント送信・ステータス変更 | チャット画面からコメント送信とステータス変更ができる | 送信後にチャット履歴が更新される |
| P4 | 未読バッジ・一覧ページ拡充 | 未読判定ロジックとバッジを一覧に追加 | 未読の問い合わせにバッジが表示される |
| P5 | 動作確認・調整 | 全体の動作確認、UI微調整 | 仕様書のチェックリスト全項目が完了 |

---

## P1: チャット詳細ページ作成

### 目的

一覧テーブルからクリックして遷移できる修正依頼の詳細ページを作成する。Filament の ViewRecord ページ（またはカスタムページ）として実装し、問い合わせのメタ情報を表示する。

### 実装手順

1. **ViewDocumentChangeRequest ページ作成**
   - `app/Filament/Resources/DocumentChangeRequestsResource/Pages/ViewDocumentChangeRequest.php`
   - Filament の `ViewRecord` を拡張、またはカスタムページとして実装
   - ヘッダーに問い合わせのメタ情報を表示:
     - request_uuid
     - 取引先名 (`invoiceDocument.partner_name`)
     - 文書種別 (`invoiceDocument.document_type`)
     - 依頼種別 (`request_type`)
     - ステータス
     - 依頼日時

2. **Resource にルート追加**
   - `DocumentChangeRequestsResource::getPages()` に `view` ページを追加
   - 一覧テーブルの行クリックで詳細ページに遷移するよう設定（`$table->recordUrl()` または行アクションで遷移）

3. **一覧テーブルのアクション整理**
   - 既存のモーダル「詳細」アクションを詳細ページへのリンクアクションに変更
   - 「ステータス変更」「コメント投稿」モーダルアクションは詳細ページ側に移動するため、一覧からは削除（P3で実装）

### 修正対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Filament/Resources/DocumentChangeRequestsResource/Pages/ViewDocumentChangeRequest.php` | 新規作成 |
| `app/Filament/Resources/DocumentChangeRequestsResource.php` | getPages() にviewルート追加、テーブルアクション整理 |

### 完了条件

- `/admin/document-change-requests/{id}` にアクセスすると詳細ページが表示される
- 一覧テーブルから行クリックまたはアクションボタンで詳細ページに遷移できる
- 詳細ページに問い合わせのメタ情報が正しく表示される

---

## P2: チャットUI実装

### 目的

詳細ページにチャットバブル形式のコメント表示UIを実装する。

### 実装手順

1. **Livewire コンポーネント作成**
   - `app/Livewire/ChatPanel.php`
   - プロパティ: `$requestId` (int)
   - マウント時に `DocumentChangeRequestClient::getComments()` でコメント取得
   - コメント者名の解決ロジック実装

2. **チャットUIテンプレート作成**
   - `resources/views/livewire/chat-panel.blade.php`
   - レイアウト仕様:
     - **顧客コメント** (`partner_user`): 左寄せ、灰色背景（`bg-gray-100`）
     - **管理側コメント** (`company_user`): 右寄せ、青系背景（`bg-blue-100`）
     - **システムメッセージ** (`system`): 中央寄せ、小文字
   - 各バブルに表示: コメント者名、本文、投稿日時
   - スクロール可能なチャットエリア（最新が下に表示）

3. **コメント者名の解決**
   - `DocumentChangeCommentClient` にコメント者名解決メソッド追加
   - `company_user` → ローカルの `users` テーブル（sakemaru接続）から名前取得
   - `partner_user` → `partner_users` テーブル（invoice接続）から名前取得
   - `system` → 「システム」固定表示
   - 名前が取得できない場合のフォールバック: `{commenter_type}#{commenter_id}`

4. **詳細ページにチャットパネルを埋め込み**
   - ViewDocumentChangeRequest ページのビューにLivewireコンポーネントを配置

### 修正対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Livewire/ChatPanel.php` | 新規作成 |
| `resources/views/livewire/chat-panel.blade.php` | 新規作成 |
| `app/Services/DocumentChangeCommentClient.php` | コメント者名解決メソッド追加 |
| `app/Filament/Resources/DocumentChangeRequestsResource/Pages/ViewDocumentChangeRequest.php` | チャットパネル埋め込み |

### 完了条件

- コメントがバブル形式で表示される（左:顧客、右:管理側、中央:システム）
- コメント者名が正しく解決・表示される
- チャットエリアがスクロール可能

---

## P3: コメント送信・ステータス変更

### 目的

チャット画面からコメント送信とステータス変更を行えるようにする。

### 実装手順

1. **コメント送信フォーム**
   - ChatPanel Livewire コンポーネントにテキストエリアと送信ボタンを追加
   - プロパティ: `$newMessage` (string, max 5000文字)
   - 送信時: `DocumentChangeCommentClient::post()` を呼び出し
   - 送信後: コメント一覧を再取得し、UIを更新
   - バリデーション: 空白チェック、最大文字数チェック

2. **ステータス変更UI**
   - チャット画面にステータス変更ドロップダウンを配置
   - 現在のステータスを表示
   - 管理側が操作可能な遷移:
     - `open` → `in_review`
     - `in_review` → `resolved` / `rejected`
     - `open` → `rejected`
   - 変更時: `DocumentChangeRequestClient::updateStatus()` を呼び出し
   - `resolved` / `rejected` 選択時は `resolved_at` を設定（サービス側で対応）

3. **`resolved_at` 設定の追加**
   - `DocumentChangeRequestClient::updateStatus()` を拡張
   - `resolved` / `rejected` の場合は `resolved_at = now()` を設定
   - それ以外は `resolved_at = null` に設定

### 修正対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Livewire/ChatPanel.php` | 送信フォーム・ステータス変更ロジック追加 |
| `resources/views/livewire/chat-panel.blade.php` | 送信フォームUI・ステータスドロップダウン追加 |
| `app/Services/DocumentChangeRequestClient.php` | updateStatus() に resolved_at 設定を追加 |

### 完了条件

- テキストエリアにメッセージ入力後、送信ボタンでコメントが投稿される
- 投稿後のコメントがチャット履歴に即反映される
- ステータスドロップダウンで変更が正しく保存される
- `resolved` / `rejected` 選択時に `resolved_at` が設定される

---

## P4: 未読バッジ・一覧ページ拡充

### 目的

一覧テーブルに未読バッジを追加し、管理側が確認すべき問い合わせを一目で把握できるようにする。

### 実装手順

1. **未読判定ロジック実装**
   - `DocumentChangeRequestClient` に未読判定メソッドを追加
   - ロジック（仕様書 Section 5 に記載）:
     - `partner_user` の最新コメント日時 > `company_user` の最新コメント日時 → 未読
     - 管理側コメントが無く、顧客コメントがある → 未読
   - 一覧取得時にバッチで判定（N+1 回避）

2. **一覧テーブルに未読バッジ追加**
   - `DocumentChangeRequestsResource::table()` に未読カラムまたはバッジ追加
   - 未読の行を視覚的に区別（太字、バッジ、背景色など）

3. **一覧テーブルのカラム拡充**
   - description（依頼内容）のプレビュー表示追加
   - 最終コメント日時の表示追加
   - request_type の表示追加

### 修正対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Services/DocumentChangeRequestClient.php` | 未読判定メソッド追加 |
| `app/Filament/Resources/DocumentChangeRequestsResource.php` | 未読バッジ・カラム追加 |

### 完了条件

- 未読の問い合わせに視覚的なバッジが表示される
- 管理側がコメント投稿後に未読バッジが消える
- 一覧テーブルに依頼内容プレビューと最終コメント日時が表示される

---

## P5: 動作確認・調整

### 目的

全体の動作確認とUI微調整を行い、仕様書のチェックリストを完了させる。

### 確認手順

1. **機能チェックリスト**（仕様書 Section 実装チェックリスト）
   - [x] `invoice_read` / `invoice_write` 接続設定（既存）
   - [ ] 問い合わせ一覧ページ（client_id フィルタリング）
   - [ ] 問い合わせ詳細 + チャットUI
   - [ ] コメント送信機能（company_user として書き込み）
   - [ ] ステータス変更機能
   - [ ] 未読バッジ表示
   - [ ] コメント者名の解決

2. **UI確認**
   - チャットバブルのレスポンシブ対応
   - 長文コメントの表示崩れチェック
   - 空のコメント一覧の表示
   - ステータスバッジの色分け

3. **エッジケース確認**
   - コメントが0件の問い合わせ
   - コメント者が削除されたユーザーの場合
   - ステータスが canceled の問い合わせ（管理側から変更不可）
   - 同時書き込みの考慮（楽観的更新で十分）

4. **コード品質**
   - 不要になったモーダルアクションのコード削除
   - PHPStan / Pint による静的解析
   - テスト追加が必要な場合は実施

### 完了条件

- 仕様書のチェックリスト全項目にチェックが入る
- 主要な操作フロー（一覧→詳細→コメント送信→ステータス変更→一覧に戻る）が問題なく動作する

---

## 制約（厳守）

1. **DB接続ルール**: 読み取りは `invoice_read`、書き込みは `invoice_write` を使用する
2. **FK制約禁止**: マイグレーションで外部キー制約を追加しない（外部DB参照のため）
3. **既存テーブル変更禁止**: `document_change_requests` / `document_change_comments` のスキーマ変更はしない（別アプリのDB）
4. **commenter_type固定**: 管理側からのコメントは必ず `commenter_type = 'company_user'` を使用する
5. **テナンシー**: client_id によるフィルタリングを適用する（必要に応じて）
6. **Filament 4 パターン**: Schema ベースの Form/Table 定義に従う

## 全体完了条件

- 仕様書（chat-spec.md）の実装チェックリスト全項目完了
- 一覧→詳細→コメント送信→ステータス変更→一覧復帰の操作フローが正常動作
- 未読バッジが正しく表示・更新される
- コメント者名が正しく解決される
- 既存の一覧テーブル機能（フィルタ、ソート、検索）が引き続き動作する
