# S6: company統合連携設計 v1

- ステータス: finalized
- 目的: 自動統合せず、統合候補を安全に供給

## 連携範囲

- 同期責務は「候補生成」まで。
- 最終統合確定は `/admin` 側の運用判断。

## 候補生成キー

- `partner_code`
- `buyer.company_id`
- `normalized_name_key`
- 補助: `store_code`

## 候補ランク

- high: `company_id` 一致
- medium: `partner_code + normalized_name_key` 一致
- low: `normalized_name_key` のみ一致

## 衝突管理

- 複数候補は `doc_sync_mappings.mapping_status = manual_review`。
- 自動確定は high のみ許可（段階導入中はOFF推奨）。

## 検証

- 複数client跨ぎ同一実体で候補抽出できる。
- 衝突時に自動上書きが発生しない。

## ロールバック

- 候補生成機能を停止し、既存mappingのみ参照。

## リスク

- 中: 類似名称による誤候補。
- 低: 候補不足による手動負荷増。
