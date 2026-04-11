# S5: 書き込み戦略（upsert/chunk/tx）v1

- ステータス: finalized
- 目的: 冪等・再実行可能・低停止影響

## 書き込み単位

- トランザクション境界: `client_id + entity_type + chunk`。
- chunk サイズ:
  - partners/buyers: 500
  - buyer_invoices: 200
- タイムアウト: 30s/tx

## upsertキー

- partner: `(client_id, partner_id)`
- buyer: `(client_id, buyer_id)`
- invoice: `(client_id, invoice_id)`
- mapping: `(entity_type, source_client_id, source_id, target_system)`

## 再試行

- retryable error のみ最大3回（指数バックオフ）。
- 非retryableは即 `doc_sync_errors` 記録。

## 整合/並列

- 同一 `client_id + entity_type` の並列実行は禁止（アプリロック）。
- checkpoint 更新は run 完了時にのみ commit。

## 検証

- 同一データで2回 apply して 2回目更新件数が0に収束。
- 故意に中断後、再実行で欠損なく回復。

## ロールバック

- apply停止、dry-run のみに切替。
- chunkサイズを縮小し再開（500->100 など）。

## リスク

- 高: lock競合や長大txによる遅延。
- 中: retry誤設定による重複負荷。
