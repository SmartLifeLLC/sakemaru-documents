# S7: Admin UI設計（帳場側）v1

- ステータス: finalized
- 目的: 実行・監視・再実行をUIで完結

## 画面

1. Sync Dashboard
2. Run Detail
3. Error Queue
4. Checkpoint Control

## 必須機能

- 手動同期（dry-run/apply）
- client単位再同期
- 実行履歴一覧（status, 件数, 所要時間）
- エラー詳細（entity, source key, retryable）
- checkpoint リセット（entity/client単位）

## ガード

- apply 実行は role 制限。
- 同一client実行中は二重起動不可。
- checkpoint リセットは確認ダイアログ + 監査ログ必須。

## 検証

- UI操作のみで `実行 -> 結果確認 -> 再実行` が可能。
- ガード条件で危険操作がブロックされる。

## ロールバック

- UI機能フラグOFFでジョブ起動導線を無効化。

## リスク

- 低: 操作導線不足による運用ミス。
- 中: 権限制御漏れ。
