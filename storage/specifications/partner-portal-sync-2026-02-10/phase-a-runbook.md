# Phase A Runbook: doc_管理テーブル導入（非稼働）

- 対象日: 2026-02-11
- 目的: 既存運用を止めず、同期管理テーブルを先行導入
- 対象: `doc_sync_runs`, `doc_sync_run_items`, `doc_sync_errors`, `doc_sync_checkpoints`, `doc_sync_mappings`

## 前提チェック

1. アプリ側で新テーブル参照機能が無効（feature flag OFF）
2. 既存バッチと競合しないメンテナンス時間帯を選定
3. DBバックアップ取得済み

## 実施順序

1. migration 作成（1テーブルずつ、5本）
2. ローカル適用で DDL 検証
3. staging 適用で migrate/rollback 検証
4. production で migrate 実行（非稼働）
5. テーブル定義・index・unique の目視確認

## 成功条件

- migration が全環境で成功
- 既存機能の回帰なし
- 新テーブルへの参照/書込が発生していない（非稼働）

## 失敗時対応

- migrate失敗: 原因修正後、同フェーズで再実行
- 部分適用: 該当 migration を rollback して再適用
- 本番影響: 直ちに新migrationの適用停止、feature flag OFF維持

## ロールバック

1. `migrate:rollback --step=5`（Phase A分）
2. schema 差分がないことを確認
3. 監査記録に rollback 理由を残す

## リスク

- medium: index作成時I/O増加
- low: 非稼働導入のため機能影響は限定的
