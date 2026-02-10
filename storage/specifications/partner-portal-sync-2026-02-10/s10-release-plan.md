# S10: リリース計画 v1

- ステータス: finalized
- 目的: 既存運用を止めず段階展開

## フェーズ

- Phase A: `doc_` 管理テーブル導入（非稼働）
- Phase B: dry-run 運用（全client/夜間）
- Phase C: 限定client apply
- Phase D: 全client apply

## 各フェーズのゲート

- 件数差異率
- error率
- 平均処理時間
- 運用承認（担当者チェック）

## ロールバック戦略

- 即時: apply停止、dry-runへ切替
- 段階: 直前フェーズに戻す
- checkpoint は直前安定runへ復元可能に保持

## 検証

- 各Phaseでゲート条件を満たした場合のみ次に進む。
- 重大障害時に30分以内で停止・復元判断ができる。

## リスク

- 中: 限定clientで顕在化しない問題の全体展開時露見。
- 低: 運用承認の遅延。
