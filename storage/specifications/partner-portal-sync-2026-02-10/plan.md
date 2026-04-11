# 酒丸帳場 → お得意先ポータル 同期実装方針（Planner）

## 0. スコープ

対象リポジトリ:
- `/Users/jungsinyu/Projects/sakemaru-documents`

対象業務:
- 酒丸基幹（同一DB）から `clients / partners / buyers / buyer_invoices / client_settings` を抽出
- `sakemaru-partner-invoice` が利用できる形で同期データを供給
- 酒丸帳場側に同期実行・監視UIを提供

非スコープ:
- 本ドキュメントで実装コードは書かない
- 本システム (`sakemaru-partner-invoice`) 側の画面改修詳細は別タスク

---

## 1. 同期設計のゴール

1. `partners(is_supplier=false)` を確実に同期対象化する
2. `buyers.partner_id` 整合を担保したうえで請求対象得意先を表現する
3. `buyer_invoices` を冪等同期できる
4. 本システムの `company` 統合運用に必要な識別情報を保持する
5. 障害時に再実行可能で、二重反映しない
6. 同期状態を Admin UI で運用監視できる

---

## 2. 実測スキーマ反映ポイント（2026-02-10）

- `partners.name` は generated column
  - 名前構成列（`name_symbol/name_area/name_main/name_store`）も保存対象候補
- `partners.code` は client 内コードとして利用可能
- `buyers` は `partner_id` を持つが、件数が `partners(is_supplier=0)` と一致しない可能性がある
- `buyer_invoices` は `client_id + partner_id` インデックスを持つ
  - 差分抽出の主軸に使える
- `client_settings` は実測で1行（要件では client ごと1行前提）

---

## 3. 実行ステップ（原子的）

### S1. 同期契約（canonical data contract）を定義する

- 内容:
  - source項目 → 同期項目のマッピング定義
  - 必須・任意項目、NULL許容、型、更新優先度を固定
  - `partner` 識別キーを `client_id + partner_id + code` で定義
- verification:
  - 契約表で `clients/partners/buyers/buyer_invoices` 全列の扱いが未定義ゼロ
- rollback:
  - 契約版を破棄し前版へ戻す
- risk:
  - 中（後続全工程に影響）
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s1-contract.md`

### S2. `doc_` 同期管理テーブル仕様を定義する

- 内容:
  - `doc_sync_runs`, `doc_sync_run_items`, `doc_sync_errors`, `doc_sync_checkpoints`, `doc_sync_mappings` の役割を定義
  - 各テーブルのPK/ユニークキー/保持期間/論理削除方針を定義
- verification:
  - 1回の同期実行を run単位で追跡し、成功/失敗/再実行理由が説明可能
- rollback:
  - 仕様採択前のため差分破棄可能
- risk:
  - 中
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s2-doc-sync-tables.md`

### S3. 抽出クエリ戦略を定義する

- 内容:
  - `partners where is_supplier=false` を一次集合とする
  - `buyers` は `partner_id` join で付帯情報取得
  - `buyer_invoices` は `updated_at` と checkpoint で差分抽出
  - `client_settings` は client 単位の付帯設定として join
- verification:
  - サンプルclientで件数突合（source件数 = 抽出件数）
- rollback:
  - 抽出条件を前バージョンへ戻す
- risk:
  - 高（抽出漏れ/過抽出で請求漏れや越境リスク）
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s3-extraction-strategy.md`

### S4. 変換・正規化ルールを定義する

- 内容:
  - partner名の正規化方針（generated名 + 構成要素）
  - ステータス・enum 値の変換表
  - 監査用に source主キー群（`client_id/partner_id/buyer_id/invoice_id`）を保持
- verification:
  - 変換前後で識別キー喪失がないこと
- rollback:
  - 変換ルールセットを前版へ戻す
- risk:
  - 中
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s4-transformation-normalization.md`

### S5. 書き込み（upsert）戦略を定義する

- 内容:
  - 直接DB書き込み時のトランザクション単位を定義
  - chunkサイズ、タイムアウト、リトライ回数を定義
  - idempotent upsertキーを tableごとに定義
- verification:
  - 同期ジョブを2回連続実行して差分ゼロを確認
- rollback:
  - 旧書き込み戦略に戻す（batch停止→定義戻し）
- risk:
  - 高（重複/欠落/ロック）
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s5-write-upsert-strategy.md`

### S6. company統合連携情報の同期方針を定義する

- 内容:
  - 本システム `/admin` の company統合に必要な同定情報を同期
  - 候補キー例: `partner code`, `buyer.company_id`, 名称正規化キー
  - 自動統合せず「候補提示」までを同期責務とする
- verification:
  - 同一実体（例: 日高屋日暮里）を複数client跨ぎで候補抽出できる
- rollback:
  - 統合候補生成を停止し、単純同期のみへ戻す
- risk:
  - 中
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s6-company-integration-linkage.md`

### S7. 酒丸帳場 Admin UI（同期運用UI）仕様を定義する

- 内容:
  - 手動同期実行
  - client単位再同期
  - 実行履歴/件数/所要時間/エラー表示
  - checkpointリセット
  - dry-run（書き込みなし差分確認）
- verification:
  - UI操作だけで「実行→結果確認→再実行」まで完了できる
- rollback:
  - UI機能フラグOFFで運用無効化
- risk:
  - 低
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s7-admin-ui-spec.md`

### S8. 障害対応・再実行手順を定義する

- 内容:
  - 部分失敗時の再開点
  - 失敗レコード隔離（dead-letter相当）
  - ロック競合時の待機/中断基準
- verification:
  - 想定障害シナリオ（DB切断/重複キー/データ欠損）で復旧手順が一意
- rollback:
  - 同期停止 + checkpoint固定で影響拡大防止
- risk:
  - 中
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s8-failure-recovery.md`

### S9. テスト計画を定義する

- 内容:
  - 単体: 変換/キー生成/差分判定
  - 結合: 抽出→変換→書込
  - E2E: UIから実行して本システム反映確認
  - データ整合: source件数・target件数突合
- verification:
  - テストケースで「未検証領域なし」を確認
- rollback:
  - 既存運用テストセットに戻す
- risk:
  - 低
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s9-test-plan.md`

### S10. リリース計画を定義する

- 内容:
  - Phase A: `doc_` 管理テーブル導入（非稼働）
  - Phase B: dry-run運用
  - Phase C: 限定clientで本同期
  - Phase D: 全client展開
- verification:
  - 各Phaseで判定基準（件数差、エラー率、処理時間）を満たす
- rollback:
  - 直前Phaseへ戻す（ジョブ停止 + checkpoint固定）
- risk:
  - 中
- artifact:
  - `storage/specifications/partner-portal-sync-2026-02-10/s10-release-plan.md`

---

## 4. 受け入れ条件

- 同期契約・抽出・変換・書込・UI・障害復旧・テスト・リリースが全て定義済み
- 各ステップに `verification / rollback / risk` がある
- 本要件を満たす:
  - `partners(is_supplier=false)` 同期
  - `buyers(partner_id)` 紐付け同期
  - `buyer_invoices` 同期
  - 本システム `/admin` の company統合運用に連携可能

---

## 5. 実装着手順（次アクション）

1. S1〜S10 を実装チケットへ分解（1チケット1検証単位）
2. Phase A（管理テーブル導入・非稼働）から着手
3. dry-run先行で運用計測し、apply判定基準を固定
4. 限定client展開後に全体展開判定
