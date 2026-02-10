# S8: 障害復旧・再実行設計 v1

- ステータス: finalized
- 目的: 部分失敗からの確実な再開

## 障害分類

- DB接続断
- 制約違反（重複キー/外部キー）
- データ不整合（orphan/欠損）
- ロック競合

## 復旧ルール

- retryable: 自動再試行（最大3回）
- 非retryable: `doc_sync_errors` に隔離し run は partial 完了
- 再実行は checkpoint から再開

## dead-letter相当

- `doc_sync_errors` を未解決キューとして運用
- 解決後は `resolved_at` 更新、再実行対象へ戻す

## 停止基準

- 同一runで error率が閾値（例: 5%）超過で自動停止
- DB負荷閾値超過時は chunk 縮小または停止

## 検証

- 障害注入（接続断/重複/欠損）で復旧手順が一意。
- 再実行で重複反映が発生しない。

## ロールバック

- 同期停止 + checkpoint固定。
- 直前安定runの checkpoint に手動復元。

## リスク

- 中: 閾値設定不適切で停止過多/停止不足。
