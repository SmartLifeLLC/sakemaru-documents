# S3: 抽出SQL設計 v1

- ステータス: finalized
- 目的: 漏れなく・越境なく・再実行可能な差分抽出

## 抽出方針

1. 基準集合は `partners where is_supplier = 0`。
2. `buyers` は `buyers.partner_id = partners.id AND buyers.client_id = partners.client_id` で結合。
3. `buyer_invoices` は `client_id + partner_id + updated_at` を主軸に checkpoint 差分抽出。
4. `client_settings` は `client_id` 単位で付帯取得（欠損は warning）。

## entity別クエリ条件

- partners:
  - filter: `is_supplier = 0`
  - optional filter: `client_id = :client_id`（限定同期時）
- buyers:
  - join対象は同期対象partnerのみに限定
- buyer_invoices:
  - join対象は同期対象partnerのみに限定
  - 差分: `(updated_at > :cursor_updated_at) OR (updated_at = :cursor_updated_at AND id > :cursor_id)`

## 抽出順序

1. client
2. client_settings
3. partners
4. buyers
5. buyer_invoices

## 検証

- client単位で source件数と抽出件数の一致を確認。
- orphan (`buyers.partner_id` 不整合) が `doc_sync_errors` に隔離される。
- checkpoint 指定で再実行した場合、前回までの対象を重複抽出しない。

## ロールバック

- 差分抽出条件を checkpoint 無効の full-scan に一時切替。
- 抽出対象を `partners` のみに絞る縮退運転を許可。

## リスク

- 高: checkpoint境界の誤りで漏れ/重複。
- 中: join条件不足で client越境。
