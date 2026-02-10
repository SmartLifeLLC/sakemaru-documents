# Phase B Gates: dry-run 判定基準

- 対象日: 2026-02-11
- 目的: apply移行判定の定量基準を固定

## 観測期間

- 最低 5 連続run（同一条件）

## ゲート基準

1. 件数差異率
- `abs(source_count - extracted_count) / source_count <= 0.1%`

2. エラー率
- `error_count / scanned_count <= 0.5%`
- retryable 以外のエラーは 0 を目標

3. 処理時間
- p95 実行時間が基準値以内
- 初期基準: 10,000件あたり 5分以内

4. 越境
- client越境検知 0件

5. 冪等性
- 同一checkpointの再dry-runで追加差分 0件

## 判定

- 全項目を満たした場合のみ Phase C（限定client apply）へ進む。
- 1項目でも未達なら Phase B 継続。

## ロールバック

- 基準未達時は apply進行を禁止し、原因カテゴリ別に修正チケット化。

## リスク

- medium: 基準が厳しすぎると移行停滞。
- medium: 基準が緩すぎると本番不具合流入。
