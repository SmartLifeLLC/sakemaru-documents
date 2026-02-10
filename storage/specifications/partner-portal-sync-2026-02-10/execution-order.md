# Execution Order: S1-S10 (Sequential)

- 作成日: 2026-02-11
- 方針: 1ステップずつ実施し、各ステップの検証完了後に次へ進む
- 制約: 破壊的変更禁止 / 既存運用停止なし / 冪等性・再実行性最優先

## Stage 1: 計画確定（完了）

1. S1 同期契約定義: completed
2. S2 doc_同期管理テーブル設計: completed
3. S3 抽出SQL設計: completed
4. S4 変換・正規化設計: completed
5. S5 書き込み戦略設計: completed
6. S6 company統合連携設計: completed
7. S7 Admin UI設計: completed
8. S8 障害復旧・再実行設計: completed
9. S9 テスト計画: completed
10. S10 リリース計画: completed

## Stage 2: 実装準備（次に実施）

1. T01: 実装チケット作成（S1-S10対応）
- verification: 全チケットに scope/verification/rollback/risk がある
- rollback: 未着手チケットは破棄可能
- risk: low

2. T02: Phase A 実施手順固定（doc_テーブル導入のみ）
- verification: テーブル導入が非稼働で既存運用無影響
- rollback: 機能フラグで参照停止
- risk: medium

3. T03: Phase B 判定基準の数値固定（dry-run）
- verification: 件数差異率/error率/処理時間の閾値が明文化
- rollback: 閾値を暫定値へ戻す
- risk: medium

## Stage 3: 実装フェーズ（順次）

1. P-A1: `doc_sync_runs` migration (completed: 2026-02-11)
2. P-A2: `doc_sync_run_items` migration (completed: 2026-02-11)
3. P-A3: `doc_sync_errors` migration (completed: 2026-02-11)
4. P-A4: `doc_sync_checkpoints` migration (completed: 2026-02-11)
5. P-A5: `doc_sync_mappings` migration (completed: 2026-02-11)
6. P-A6: migration apply + validate（non-functional）(completed: 2026-02-11)

- verification (共通): migrate/rollback テスト、既存機能回帰なし
- rollback (共通): migrate rollback + feature flag OFF
- risk (共通): medium

## Stage 4: 運用導入フェーズ（順次）

1. P-B1: dry-run 実行経路を有効化 (completed: 2026-02-11)
2. P-B2: 監視指標計測 (completed: 2026-02-11)
3. P-C1: 限定client apply (completed: 2026-02-11)
4. P-D1: 全client展開 (completed: 2026-02-11)

- verification (共通): フェーズゲート達成
- rollback (共通): 直前フェーズへ復帰
- risk (共通): high

## 実施ルール

- 同時並行はしない（single-flight）。
- 1項目完了ごとに `boot.md` と本ファイルを更新。
- 検証未完了の項目は completed にしない。

## 補足

- Stage 4 の apply は段階導入として `partners(is_supplier=0)` と `buyer_invoices` まで実装済み。
- `buyers` は target 側に対応テーブルがないため同期対象外（契約・監査対象として継続管理）。
- `doc_sync_checkpoints` を使った継続実行を実装済み（`--from_start` で先頭再実行可能）。
- 運用コマンド: `sync:partner-portal:checkpoint-show`, `sync:partner-portal:checkpoint-reset`。
- Admin運用UI: `/admin/sync-runs`, `/admin/sync-run-items`, `/admin/sync-errors`, `/admin/sync-checkpoints`, `/admin/sync-mappings`。
- 同期サービス検証テストを追加済み: `tests/Feature/Sync/*`（apply/invoice apply/gate）。
- 運用制約として、今後 `sakemaru` への新規 migration は追加しない。
