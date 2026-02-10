# Implementation Tickets (Atomic)

- 作成日: 2026-02-11
- ルール: 各チケットは独立検証可能・可逆・最小影響

## TKT-001 (S2)
- title: Create migration for `doc_sync_runs`
- verification: migrate/rollback 成功、テーブル存在確認
- rollback: migrate:rollback 1 step
- risk: medium

## TKT-002 (S2)
- title: Create migration for `doc_sync_run_items` + unique/index
- verification: 重複投入で unique 制約確認
- rollback: migrate:rollback 1 step
- risk: medium

## TKT-003 (S2)
- title: Create migration for `doc_sync_errors`
- verification: 検索インデックス有効確認
- rollback: migrate:rollback 1 step
- risk: low

## TKT-004 (S2)
- title: Create migration for `doc_sync_checkpoints`
- verification: upsertで1行更新確認
- rollback: migrate:rollback 1 step
- risk: medium

## TKT-005 (S2/S6)
- title: Create migration for `doc_sync_mappings`
- verification: source-target unique と逆引きindex確認
- rollback: migrate:rollback 1 step
- risk: medium

## TKT-006 (S1/S3)
- title: Define extraction query objects for partners/buyers/invoices
- verification: sample clientで件数一致
- rollback: query selector を旧full scanへ戻す
- risk: high

## TKT-007 (S4)
- title: Implement normalization/enum mapping layer
- verification: 同一入力でhash一致
- rollback: 変換レイヤー feature flag OFF
- risk: medium

## TKT-008 (S5)
- title: Implement idempotent upsert writer with chunk tx
- verification: 2回実行で2回目差分収束
- rollback: apply停止 + dry-runのみ
- risk: high

## TKT-009 (S8)
- title: Implement error isolation and retry policy
- verification: retryable/non-retryable 分岐テスト
- rollback: retry無効化
- risk: medium

## TKT-010 (S7)
- title: Implement admin sync dashboard (run/list/detail)
- verification: UIからrun->result確認
- rollback: UI feature flag OFF
- risk: medium

## TKT-011 (S7/S8)
- title: Implement error queue + checkpoint reset UI
- verification: reset操作の監査ログ確認
- rollback: 操作エンドポイント無効化
- risk: medium

## TKT-012 (S9)
- title: Add unit/integration/e2e test suites for sync
- verification: テスト全通過、越境/重複/漏れ0
- rollback: 新規テスト群のみ除外
- risk: low

## TKT-013 (S10)
- title: Phase gates and rollout runbook
- verification: gate判定が文書化・実行可能
- rollback: 前フェーズrunbookへ戻す
- risk: medium
