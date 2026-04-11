# S2: doc_ 同期管理テーブル設計 v1

- 対象日: 2026-02-10
- ステータス: finalized
- 方針: 破壊的変更なし、段階導入、冪等/再実行優先

## 1. doc_sync_runs

- 目的: 同期実行の単位管理（監査・統計）
- PK: `id` (bigint)
- 主カラム:
  - `sync_scope` (varchar)
  - `mode` (enum: `dry_run`/`apply`)
  - `status` (enum: `running`/`success`/`failed`/`partial`)
  - `client_id` (nullable bigint)
  - `started_at`, `finished_at` (datetime)
  - `checkpoint_from`, `checkpoint_to` (json)
  - `scanned_count`, `upsert_count`, `skip_count`, `error_count` (bigint)
- Unique: なし
- Index:
  - `(sync_scope, created_at desc)`
  - `(status, created_at desc)`
  - `(client_id, created_at desc)`

## 2. doc_sync_run_items

- 目的: レコード単位の処理結果と冪等制御
- PK: `id` (bigint)
- 主カラム:
  - `run_id` (bigint)
  - `entity_type` (varchar)
  - `source_client_id` (bigint)
  - `source_pk` (varchar)
  - `operation` (enum: `insert`/`update`/`skip`)
  - `idempotency_key` (char(64))
  - `payload_hash` (char(64))
  - `result_status` (enum: `success`/`failed`/`skipped`)
  - `attempt_count` (int)
  - `processed_at` (datetime)
- Unique:
  - `(run_id, entity_type, source_client_id, source_pk)`
- Index:
  - `(run_id, result_status)`
  - `(entity_type, source_client_id, source_pk)`
  - `(idempotency_key)`

## 3. doc_sync_errors

- 目的: 失敗詳細・再試行可否・解決状態管理
- PK: `id` (bigint)
- 主カラム:
  - `run_id` (bigint)
  - `run_item_id` (nullable bigint)
  - `entity_type` (varchar)
  - `source_client_id` (bigint)
  - `source_pk` (varchar)
  - `stage` (varchar)
  - `error_code` (varchar)
  - `error_message` (text)
  - `error_context` (json)
  - `is_retryable` (bool)
  - `first_seen_at`, `last_seen_at`, `resolved_at` (datetime)
- Unique: なし
- Index:
  - `(run_id, last_seen_at desc)`
  - `(resolved_at, is_retryable, last_seen_at)`
  - `(entity_type, source_client_id, source_pk)`

## 4. doc_sync_checkpoints

- 目的: 差分同期の再開点管理
- PK: `id` (bigint)
- 主カラム:
  - `sync_scope` (varchar)
  - `entity_type` (varchar)
  - `client_id` (bigint)
  - `cursor_updated_at` (datetime)
  - `cursor_id` (bigint)
  - `last_run_id` (bigint)
  - `lock_version` (int)
- Unique:
  - `(sync_scope, entity_type, client_id)`
- Index:
  - `(last_run_id)`
  - `(updated_at)`

## 5. doc_sync_mappings

- 目的: source-target 対応関係・統合候補管理
- PK: `id` (bigint)
- 主カラム:
  - `entity_type` (varchar)
  - `source_client_id` (bigint)
  - `source_id` (varchar)
  - `source_code` (nullable varchar)
  - `target_system` (varchar)
  - `target_id` (nullable varchar)
  - `mapping_status` (enum: `active`/`stale`/`conflict`/`manual_review`)
  - `confidence` (nullable decimal)
  - `first_synced_at`, `last_synced_at`, `stale_at` (datetime)
- Unique:
  - `(entity_type, source_client_id, source_id, target_system)`
- Index:
  - `(target_system, target_id)`
  - `(mapping_status, last_synced_at desc)`
  - `(entity_type, source_client_id, source_code)`

## 6. 保持期間

- `doc_sync_runs`: 180日
- `doc_sync_run_items`: 90日
- `doc_sync_errors`: 未解決は無期限、解決済み180日
- `doc_sync_checkpoints`: 無期限
- `doc_sync_mappings`: 無期限（`stale` 管理）

## 7. 検証

- 1 run に対して `runs -> run_items -> errors -> checkpoints` が追跡可能。
- 同一データ再実行時に `idempotency_key` で二重反映が発生しない。
- 想定検索がインデックス利用（EXPLAINで確認）。

## 8. ロールバック

- 段階導入のため create-only で追加。
- 停止時は参照/更新フラグをOFF。
- 物理削除は即時実施しない（参照停止・保持期間経過後に検討）。

## 9. リスク

- 中: ログ肥大化によるI/O増加。
- 中: checkpoint 更新競合（同一client並列run）。
- 低: 監査要件不足による再調整。
