# Work Plan Boot: partner-portal-sync-2026-02-10

- **ID**: partner-portal-sync-2026-02-10
- **作成日**: 2026-02-10
- **最終更新**: 2026-02-11
- **ステータス**: planning-complete
- **実行モード**: sequential-in-progress
- **対象リポジトリ**: `/Users/jungsinyu/Projects/sakemaru-documents`
- **出力先**: `storage/specifications/partner-portal-sync-2026-02-10/`

## 目的

酒丸帳場（`sakemaru-documents`）が、酒丸基幹DBから以下データを取得し、
酒丸帳場-お得意先（`sakemaru-partner-invoice`）へ同期する実装方針を定義する。

- `clients`
- `partners`（`is_supplier = false` のみ対象）
- `buyers`（`partner_id` で partner と紐づく）
- `buyer_invoices`
- `client_settings`（各 client で1行を前提）

## 重要前提（ユーザー指定）

1. 酒丸帳場は「同期オーケストレーター」の役割を持つ
2. 酒丸帳場は全システムDBへ直接アクセスでき、DBへ直接書き込む
3. 酒丸と酒丸帳場は同一DBで、酒丸帳場側の管理テーブルは `doc_` prefix を使用
4. 本システム `/admin` は複数 client 統合（`company` 管理）を担う

## ローカルDB実測（tani_local_test）

接続:
- host: local
- db: `tani_local_test`
- user: `root`
- password: なし

確認日: 2026-02-10

### テーブル存在確認

- `clients` あり
- `client_settings` あり
- `partners` あり
- `buyers` あり
- `buyer_invoices` あり

### 件数確認

- `client_settings`: 1
- `partners where is_supplier = 0`: 15,851
- `buyers`: 15,850
- `buyer_invoices`: 1,521

### 主要スキーマ要点（実測）

- `clients`
  - PK: `id`
  - 主要列: `code`, `name`, `is_active`
- `client_settings`
  - 主要列: `client_id`, 複数feature flag
- `partners`
  - PK: `id`
  - 主要列: `client_id`, `code`, `is_supplier`, `is_active`
  - `name` は generated column
- `buyers`
  - PK: `id`
  - 主要列: `client_id`, `partner_id`, `company_id`, `store_code`
- `buyer_invoices`
  - PK: `id`
  - 主要列: `uuid`, `client_id`, `partner_id`, `partner_code`, `partner_name`, `status`, `closing_date`
  - 主インデックス: `(client_id, partner_id, closing_date)`

## 同期対象の論点

- `partners` は `is_supplier = false` のみ同期対象
- `buyers.partner_id` と `partners.id` の不整合検出が必要
- `buyer_invoices.partner_id` が同期対象 partner に属するか検証が必要
- 下流（本システム）の `company` 統合に対応できる識別子セットを保持する必要がある

## セッション再開手順

1. この `boot.md` を読む
2. `plan.md` を読む
3. `execution-order.md` を読む
4. 実行中タスク（`in-progress`）を確認
5. 次の未着手タスクへ進む

## 進捗

| Step | 状態 | 更新日 | 備考 |
|---|---|---|---|
| S1: 同期契約定義 | completed | 2026-02-11 | `s1-contract.md` 確定 |
| S2: doc_同期管理テーブル設計 | completed | 2026-02-11 | `s2-doc-sync-tables.md` 確定 |
| S3: 抽出SQL設計 | completed | 2026-02-11 | `s3-extraction-strategy.md` 確定 |
| S4: 変換・正規化設計 | completed | 2026-02-11 | `s4-transformation-normalization.md` 確定 |
| S5: 書き込み戦略設計 | completed | 2026-02-11 | `s5-write-upsert-strategy.md` 確定 |
| S6: company統合連携設計 | completed | 2026-02-11 | `s6-company-integration-linkage.md` 確定 |
| S7: Admin UI設計（帳場側） | completed | 2026-02-11 | `s7-admin-ui-spec.md` 確定 |
| S8: 障害復旧・再実行設計 | completed | 2026-02-11 | `s8-failure-recovery.md` 確定 |
| S9: テスト計画 | completed | 2026-02-11 | `s9-test-plan.md` 確定 |
| S10: リリース計画 | completed | 2026-02-11 | `s10-release-plan.md` 確定 |

## 完了条件（この計画フェーズ）

- `plan.md` に原子的ステップを定義
- 各ステップに `verification` `rollback` `risk` を明示
- 上記ローカルDB実測を前提に同期契約が記述される

## 実装準備の進捗（順次実施）

| Task | 状態 | 更新日 | 備考 |
|---|---|---|---|
| T01: 実装チケット作成（S1-S10対応） | completed | 2026-02-11 | `implementation-tickets.md` 確定 |
| T02: Phase A 実施手順固定（doc_テーブル導入） | completed | 2026-02-11 | `phase-a-runbook.md` 確定 |
| T03: Phase B 判定基準固定（dry-run） | completed | 2026-02-11 | `phase-b-gates.md` 確定 |

## 実装フェーズ進捗（順次実施）

| Task | 状態 | 更新日 | 備考 |
|---|---|---|---|
| P-A1: `doc_sync_runs` migration | in-progress | 2026-02-11 | Phase A先頭 |
| P-A2: `doc_sync_run_items` migration | pending | 2026-02-11 |  |
| P-A3: `doc_sync_errors` migration | pending | 2026-02-11 |  |
| P-A4: `doc_sync_checkpoints` migration | pending | 2026-02-11 |  |
| P-A5: `doc_sync_mappings` migration | pending | 2026-02-11 |  |
| P-A6: migration apply + validate | pending | 2026-02-11 | non-functional導入確認 |
