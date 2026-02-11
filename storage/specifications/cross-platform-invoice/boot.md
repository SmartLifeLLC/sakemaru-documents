# Work Plan: cross-platform-invoice-system (documents)

- **ID**: cross-platform-invoice-system-documents
- **作成日**: 2026-02-11
- **最終更新**: 2026-02-11
- **ステータス**: 進行中
- **ディレクトリ**: storage/specifications/cross-platform-invoice/
- **対象システム**: 酒丸帳場（sakemaru-documents）

## セッション再開手順

コンテキストがクリアされた場合、以下を読んで作業を再開する:

1. このファイルを読む（boot.md）
2. plan.md を読む（作業計画の全体像）
3. `/Users/jungsinyu/Projects/sakemaru-ai-core-taniguchi/storage/specifications/cross-platform-invoice/base-spec.md` を読む（3プロジェクト共通仕様）
4. `/Users/jungsinyu/Projects/sakemaru-ai-core-taniguchi/storage/specifications/cross-platform-invoice/boot.md` を読む（酒丸本家側の進捗）
5. `/Users/jungsinyu/Projects/sakemaru-partner-invoice/storage/specifications/cross-platform-invoice/boot.md` を読む（invoice側の進捗）
6. 下記「進捗」テーブルで現在のPhaseを確認
7. 「Phase完了記録」セクションで完了済みPhaseの実績を確認
8. 「作業中コンテキスト」セクションで途中データを確認
9. 未完了の最初のPhaseから plan.md の該当セクションを読んで作業再開

## 概要

社内運用画面（documents）側の対応。修正/新規作成フォームの提供、JSON組み立てと
酒丸本家 `custom_invoice_queue` への投入、修正依頼一覧の参照、コメント投稿、
公開フラグ制御を実装する。

## 前提条件

### システム間の責務
- **このシステム（documents）の責務**: 社内運用画面。修正/新規作成指示、公開制御、コメント投稿
- **酒丸本家（sakemaru）の責務**: 文書生成、`custom_invoice_queue` 処理、初期値データ提供
- **invoice（お得意先）の責務**: 顧客向け画面、修正依頼受付、コメント保存（invoice DBが正）

### 依存関係
| 依存先 | 内容 | 必要なPhase |
|--------|------|------------|
| 酒丸本家 P1 | external_invoices リネーム完了 | documents P1（初期値取得DB連携対応） |
| 酒丸本家 P3 | custom_invoice_queue テーブル稼働 | documents P3（queue投入先） |
| 酒丸本家 P5 | JSON v1バリデーション仕様確定 | documents P2（JSON組み立て） |
| 酒丸本家 P7 | 事前調査成果物（項目一覧・計算ルール） | documents P2（フォーム項目） |
| invoice P4 | document_change_requests テーブル稼働 | documents P4（依頼一覧参照） |
| invoice P5 | コメントテーブル稼働 | documents P5（コメント投稿先） |

### 連携方式
```
[documents] ─── 同一DB直接書き込み ──→ [酒丸本家] custom_invoice_queue
[documents] ─── 別DB直接書き込み ──→ [invoice] document_change_comments
[documents] ←── 同一DB直接参照 ──── [酒丸本家] external_invoices（初期値表示用）
[documents] ←── 別DB直接参照 ──── [invoice] 修正依頼一覧・コメント履歴
```

### 注意: 連携方式（確定）
- 酒丸本家連携: **同一DB直接連携**（external_invoices 参照 / custom_invoice_queue 書き込み）
- invoice連携: **別DB直接連携**（document_change_requests/comments 参照・書き込み）
- 本件のためのシステム間APIは作成しない

### migration責任（base-spec §8 固定ルール）
- documents DB の migration は documents チームが実施
- 他システム DB へ直接 migration を流さない
- custom_invoice_queue 連携は同一DBへの直接書き込みで実施する

## 重要な設計制約

- **文書生成はしない** — 生成は酒丸本家の責務（base-spec §3.1.1）
- **コメント保存先は invoice DB** — documents側から別DBへ直接書き込む（base-spec §4.2.5）
- **JSON v1 準拠** — payload_json の構造は base-spec §6.2 に従う
- **公開制御はこのシステムが起点** — 公開フラグ操作後に invoice 側へ同期
- **migrate:fresh/refresh 禁止** — 本番データ保護

## 対象ファイル（推定・実プロジェクト確認後に更新）

### 新規作成
- 修正フォーム画面（Livewire or Controller + Blade）
- 新規作成フォーム画面（文書種別ごと）
- JSON組み立てサービス（CustomInvoiceJsonBuilder）
- queue投入サービス（CustomInvoiceQueueClient）
- 修正依頼一覧/詳細画面
- コメント投稿クライアント
- 公開制御画面/連携処理

### 既存変更
- ナビゲーション: 修正/新規作成メニュー追加
- 文書一覧画面: 「修正」「新規作成」ボタン追加
- 設定/接続: 酒丸本家DB接続設定、invoice DB接続設定

### 参照のみ（変更禁止）
- 酒丸本家 `custom_invoice_queue` テーブル設計（書き込みのみ許可）
- invoice `document_change_requests` / `document_change_comments`（別DB直接連携）

---

## 進捗

| Phase | 状態 | 更新日 | 備考 |
|-------|------|--------|------|
| P0: 現状調査 | 完了 | 2026-02-11 | DB接続構成・既存画面・既存同期処理を確認 |
| P1: 初期値取得DB連携 | 完了 | 2026-02-11 | ExternalInvoiceClient をDB直接参照へ置換 |
| P2: 修正/新規作成フォーム | 完了 | 2026-02-11 | BuyerInvoicesResource に create/revise フォームを実装 |
| P3: custom_invoice_queue投入 | 完了 | 2026-02-11 | DB直INSERTの CustomInvoiceQueueClient を実装 |
| P4: 修正依頼一覧/詳細 | 完了 | 2026-02-11 | DocumentChangeRequestsResource を実装 |
| P5: コメント投稿 | 完了 | 2026-02-11 | DocumentChangeCommentClient + 画面アクションを実装 |
| P6: 公開フラグ制御 | 完了 | 2026-02-11 | DocumentPublishClient + 公開/取り消しアクションを実装 |

---

## 作業中コンテキスト

> Phase作業中に蓄積される中間データ。セッション再開時に必ず確認。

### documents プロジェクト情報（P0完了時に記入）
- リポジトリURL: /Users/jungsinyu/Projects/sakemaru-documents
- フレームワーク: Laravel 12 + Filament 4
- 既存の文書管理機能: BuyerInvoicesResource（酒丸本家DB参照）、DocumentResource（ローカル文書管理）
- DB接続設定: `config/database.php` に `sakemaru_read/sakemaru_write/invoice_read/invoice_write` を追加（既存接続へのfallback付き）

### 酒丸本家との連携情報（P0完了時に記入）
- custom_invoice_queue テーブル: (酒丸本家P3完了後に確定)
- 初期値取得: 同一DB `external_invoices` 直接参照（P1で実装）
- JSON v1 仕様: base-spec §6.2 参照

### invoice システムとの連携情報（P0完了時に記入）
- invoice DB接続名: `invoice`（要確認）
- 参照対象テーブル: `document_change_requests`, `document_change_comments`
- 認証方式: DB接続資格情報 + 最小権限（要確認）

### Git ブランチ
- 作業ブランチ: `release/v1.0`
- ベースブランチ: `main`（想定）

---

## Phase完了記録

### P0: 現状調査
- 完了日: 2026-02-11
- 実績:
  - 既存UI: `BuyerInvoicesResource` を中心に帳票管理導線が存在
  - 既存DB接続: `sakemaru` / `invoice` 接続が定義済み
  - 方針反映: DB直接連携・migrate:fresh禁止の制約を確認し、P1以降の実装方針に反映

### P1: 初期値取得DB連携
- 完了日: 2026-02-11
- 実績:
  - `app/Services/ExternalInvoiceClient.php` をDB直接参照実装へ置換（`external_invoices` を `uuid`/`closing_bill_id` で参照）
  - `app/Models/ExternalInvoice.php` を追加し、`external_invoices` を `SakemaruModel` 経由で扱う構成に整理
  - `config/database.php` と `.env.example` に read/write 分離接続（`sakemaru_read` など）を追加
  - `app/Filament/Resources/BuyerInvoicesResource.php` の既存「修正初期値」アクションがDB直接参照で初期値取得できる状態を確認
  - `tests/Feature/CrossPlatformInvoice/ExternalInvoiceClientTest.php` をDB直参照版へ置換し、5 tests / 18 assertions で成功

### P2: 修正/新規作成フォーム
- 完了日: 2026-02-11
- 実績:
  - `app/Services/CustomInvoiceJsonBuilder.php` を追加し、JSON v1組み立てを実装
  - `app/Filament/Resources/BuyerInvoicesResource.php` に
    - 新規作成フォーム（header action）
    - 修正フォーム（row action）
    - 明細リピーター（追加/削除）
    を実装
  - `requested_by_user_id` を `company_user:{id}` 形式で生成
  - テスト: `tests/Feature/CrossPlatformInvoice/CustomInvoiceJsonBuilderTest.php` 追加（2 tests）

### P3: custom_invoice_queue投入
- 完了日: 2026-02-11
- 実績:
  - `app/Services/CustomInvoiceQueueClient.php` を追加し、`custom_invoice_queue` 直接INSERTを実装
  - 重複防止: `request_context.request_uuid` を `queue_uuid` に利用（UUIDユニークで冪等）
  - `app/Filament/Resources/BuyerInvoicesResource.php` に queue登録処理と queue状態確認アクションを追加
  - 公開処理前に `queue_uuid` を入力し、status=`succeeded` を確認するガードを実装
  - テスト: `tests/Feature/CrossPlatformInvoice/CustomInvoiceQueueClientTest.php` 追加（2 tests）

### P4: 修正依頼一覧/詳細
- 完了日: 2026-02-11
- 実績:
  - `app/Models/DocumentChangeRequest.php` / `app/Models/DocumentChangeComment.php` / `app/Models/InvoiceDocument.php` を追加
  - `app/Filament/Resources/DocumentChangeRequestsResource.php` を追加
    - 一覧（status/partner_name/document_type/date フィルタ + ソート）
    - 詳細モーダル（依頼JSON+コメント履歴）
    - ステータス変更
  - `app/Services/DocumentChangeRequestClient.php` を追加（invoice DB直接参照/更新）
  - テスト: `tests/Feature/CrossPlatformInvoice/DocumentChangeClientsTest.php` 追加（1 test）

### P5: コメント投稿
- 完了日: 2026-02-11
- 実績:
  - `app/Services/DocumentChangeCommentClient.php` を追加（invoice DB直接書き込み）
  - `DocumentChangeRequestsResource` にコメント投稿アクションを追加
  - `commenter_type = company_user` / `commenter_id = auth user id` で保存

### P6: 公開フラグ制御
- 完了日: 2026-02-11
- 実績:
  - `app/Services/DocumentPublishClient.php` を追加
    - 公開プレビュー
    - invoice `documents` への upsert 公開
    - 公開取り消し（`status=canceled` / `is_active=0`）
  - `BuyerInvoicesResource` に `公開プレビュー` / `公開` / `公開取り消し` アクションを追加
  - テスト: `tests/Feature/CrossPlatformInvoice/DocumentPublishClientTest.php` 追加（1 test）

### 横断テスト
- `php artisan test tests/Feature/CrossPlatformInvoice` : 11 tests / 47 assertions すべて成功
- `php artisan test` : 25 tests / 136 assertions すべて成功（`resources/views/filament/pages/auth/custom-layout.blade.php` に `id="intro-video"` を追加して既存LoginTest整合）

### 追加対応（導線）
- 完了日: 2026-02-11
- 実績:
  - `routes/web.php` を `Route::redirect('/', '/admin');` に変更し、default URL を `/admin` に統一
  - `app/Livewire/MegaMenu.php` に `修正依頼一覧`（`DocumentChangeRequestsResource`）を追加
  - `tests/Feature/ExampleTest.php` を新仕様（`/` から `/admin` へリダイレクト）に合わせて更新

### 追加対応（不具合修正）
- 完了日: 2026-02-12
- 実績:
  - `app/Models/BuyerInvoice.php` の参照テーブル解決を互換化（`external_invoices` 優先、旧環境は `buyer_invoices` fallback）
  - `/admin` 配下の GET 画面（パラメータ不要ルート）を実URLで一括疎通し、5xx なしを確認
