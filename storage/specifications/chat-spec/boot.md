# Work Plan: chat-spec

- **ID**: chat-spec
- **作成日**: 2026-02-25
- **最終更新**: 2026-02-25
- **ステータス**: 完了
- **ディレクトリ**: storage/specifications/chat-spec/

## セッション再開手順

コンテキストがクリアされた場合、以下を読んで作業を再開する:

1. このファイルを読む（boot.md）
2. plan.md を読む（作業計画の全体像）
3. 下記「進捗」テーブルで現在のPhaseを確認
4. 「Phase完了記録」セクションで完了済みPhaseの実績を確認
5. 「作業中コンテキスト」セクションで途中データを確認
6. 未完了の最初のPhaseから plan.md の該当セクションを読んで作業再開

## 概要

既存の修正依頼一覧（`DocumentChangeRequestsResource`）のモーダル表示をチャットUI付きの詳細ページに拡張する。顧客側コメントと管理側コメントをバブル形式で表示し、コメント送信・ステータス変更・未読バッジを実装する。

## 重要な設計制約

- **DB接続**: 読み取りは `invoice_read`、書き込みは `invoice_write` を使用（既存パターン踏襲）
- **テナンシー**: `client_id` によるフィルタリングは必要に応じて実装（現状の一覧は client_id フィルタなし）
- **FK禁止**: 外部キー制約は使わない（外部DB参照のため）
- **モデル変更最小**: 既存モデル（DocumentChangeRequest, DocumentChangeComment）は必要最小限の変更のみ
- **Filament 4 パターン**: Schema、Table、Pages パターンに従う

## 対象ファイル

### 新規作成
- `app/Filament/Resources/DocumentChangeRequestsResource/Pages/ViewDocumentChangeRequest.php` — チャット詳細ページ
- `resources/views/filament/resources/document-change-requests/chat.blade.php` — チャットUIのBladeテンプレート
- `app/Livewire/ChatPanel.php` — チャットパネルのLivewireコンポーネント（必要に応じて）

### 既存変更
- `app/Filament/Resources/DocumentChangeRequestsResource.php` — 詳細ページルート追加、未読バッジ追加、テーブルカラム拡充
- `app/Services/DocumentChangeRequestClient.php` — 未読判定ロジック追加
- `app/Services/DocumentChangeCommentClient.php` — commenter名解決ロジック追加
- `app/Models/DocumentChangeComment.php` — attachments cast追加（必要に応じて）

### 参照のみ（変更禁止）
- `config/database.php` — 接続設定は既に完了
- `app/Models/Base/InvoiceModel.php` — ベースモデル
- `storage/specifications/chat-spec/chat-spec.md` — 仕様書

## テストデータ

- 実データは `invoice_read` 接続の `document_change_requests` / `document_change_comments` テーブルに存在
- テスト時は既存の修正依頼データで動作確認

---

## 進捗

| Phase | 状態 | 更新日 | 備考 |
|-------|------|--------|------|
| P0: 既存コード確認・設計方針決定 | 完了 | 2026-02-25 | 既存リソース・モデル・サービス調査済み |
| P1: チャット詳細ページ（View Page）作成 | 完了 | 2026-02-25 | ViewDocumentChangeRequest + view.blade.php 作成、一覧からリンク遷移 |
| P2: チャットUI（Blade/Livewire）実装 | 完了 | 2026-02-25 | ChatPanel Livewire + chat-panel.blade.php、コメント者名解決 |
| P3: コメント送信・ステータス変更機能 | 完了 | 2026-02-25 | 送信フォーム、ステータスドロップダウン、resolved_at対応 |
| P4: 未読バッジ・一覧ページ拡充 | 完了 | 2026-02-25 | 未読アイコン、依頼種別・内容・最終更新カラム追加 |
| P5: 動作確認・調整 | 完了 | 2026-02-25 | テスト全件パス、静的構文チェックOK |

---

## 作業中コンテキスト

> Phase作業中に蓄積される中間データ。セッション再開時に必ず確認。

### 既存実装状況（P0で確認済み）
- DocumentChangeRequestsResource: 一覧テーブル + モーダルアクション（詳細表示・ステータス変更・コメント投稿）
- DocumentChangeRequestClient: getComments(), updateStatus() 実装済み
- DocumentChangeCommentClient: post() 実装済み（company_user固定）
- モデル: DocumentChangeRequest (comments, invoiceDocument リレーション), DocumentChangeComment (request リレーション)
- DB接続: invoice_read / invoice_write 設定済み
- メニュー: MegaMenu に「修正依頼一覧」リンクあり

### Git ブランチ
- 作業ブランチ: feature/chat-detail-page
- ベースブランチ: release/v1.0

---

## Phase完了記録

### P0: 既存コード確認・設計方針決定
- 完了日: 2026-02-25
- 実績:
  - 既存ResourceはListページのみ、詳細はモーダルで表示（Textarea に plaintext で履歴表示）
  - サービス層は読み書き分離パターン確立済み
  - Filament 4 の Schema/Table パターンで構築されている
  - MegaMenu にナビゲーション登録済み

### P1: チャット詳細ページ（View Page）作成
- 完了日: 2026-02-25
- 実績:
  - `ViewDocumentChangeRequest.php` 作成（Filament Page 拡張、メタ情報ヘッダー表示）
  - `view.blade.php` 作成（問い合わせ情報セクション + チャットパネル埋め込み）
  - Resource に `view` ルート追加、テーブル行クリックで詳細ページ遷移
  - モーダルアクション（詳細/ステータス変更/コメント投稿）を詳細リンクアクションに変更

### P2: チャットUI（Blade/Livewire）実装
- 完了日: 2026-02-25
- 実績:
  - `ChatPanel.php` Livewire コンポーネント作成
  - `chat-panel.blade.php` 作成（左:顧客灰色、右:管理側青、中央:システム）
  - `DocumentChangeCommentClient::resolveCommenterNames()` 追加（バッチ名前解決）
  - company_user → sakemaru.users、partner_user → invoice.partner_users から名前取得

### P3: コメント送信・ステータス変更機能
- 完了日: 2026-02-25
- 実績:
  - ChatPanel にメッセージ送信機能追加（5000文字制限、バリデーション）
  - ステータス変更ドロップダウン追加（管理側遷移: open→in_review/rejected, in_review→resolved/rejected）
  - `DocumentChangeRequestClient::updateStatus()` に resolved_at 対応追加

### P4: 未読バッジ・一覧ページ拡充
- 完了日: 2026-02-25
- 実績:
  - `DocumentChangeRequestClient::getUnreadRequestIds()` 追加（バッチ未読判定）
  - ListPage で paginateTableQuery オーバーライドして未読ID取得
  - 一覧に未読アイコン・依頼種別・依頼内容プレビュー・最終更新カラム追加
  - ステータスバッジに色分け追加

### P5: 動作確認・調整
- 完了日: 2026-02-25
- 実績:
  - テストスキーマに resolved_at カラム追加（テスト修正）
  - 全27テストパス
  - 全PHPファイル構文チェックOK
  - 未使用インポート削除
