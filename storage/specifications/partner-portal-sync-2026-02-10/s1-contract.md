# S1: 同期契約（Canonical Data Contract）v1

- 対象日: 2026-02-10
- ステータス: finalized
- 適用スコープ: `sakemaru-documents` から `sakemaru-partner-invoice` への同期

## 1. 契約ルール

- テナント境界は常に `client_id`。
- `partners` は `is_supplier = 0` のみ同期対象。
- 冪等キーは `entity_type + source_client_id + source_pk`。
- 更新判定は `source_updated_at` 優先、同値時は `source_pk` をタイブレーク。
- 物理削除は段階導入中は反映せず、`inactive/stale` 化で扱う。

## 2. source -> target マッピング

| Entity | Source | Target (canonical) | 必須 | NULL | 型 | 備考 |
|---|---|---|---|---|---|---|
| client | `clients.id` | `client_id` | Yes | No | bigint | テナント境界キー |
| client | `clients.code` | `client_code` | Yes | No | varchar | 照合キー |
| client | `clients.name` | `client_name` | Yes | No | varchar | |
| client | `clients.is_active` | `client_active` | Yes | No | bool | false時は同期停止候補 |
| client_setting | `client_settings.client_id` | `client_id` | Yes | No | bigint | 1 client 1 row前提 |
| client_setting | `client_settings.*(必要flag)` | `feature_flags` | No | Yes | json | 不要列は同期対象外 |
| partner | `partners.id` | `partner_id` | Yes | No | bigint | |
| partner | `partners.client_id` | `client_id` | Yes | No | bigint | 越境防止 |
| partner | `partners.code` | `partner_code` | Yes | No | varchar | client内一意想定 |
| partner | `partners.is_supplier` | `is_supplier` | Yes | No | bool | 常に false のみ対象 |
| partner | `partners.is_active` | `partner_active` | Yes | No | bool | |
| partner | `partners.name` (generated) | `partner_name` | Yes | No | varchar | 表示名 |
| partner | `partners.name_symbol/name_area/name_main/name_store` | `partner_name_parts` | No | Yes | json | company統合候補用 |
| buyer | `buyers.id` | `buyer_id` | Yes | No | bigint | |
| buyer | `buyers.client_id` | `client_id` | Yes | No | bigint | 越境防止 |
| buyer | `buyers.partner_id` | `partner_id` | Yes | No | bigint | `partners.id` 参照整合必須 |
| buyer | `buyers.company_id` | `company_id` | No | Yes | bigint | 統合候補キー |
| buyer | `buyers.store_code` | `store_code` | No | Yes | varchar | |
| invoice | `buyer_invoices.id` | `invoice_id` | Yes | No | bigint | 冪等主キー |
| invoice | `buyer_invoices.uuid` | `invoice_uuid` | No | Yes | varchar | 一意補助 |
| invoice | `buyer_invoices.client_id` | `client_id` | Yes | No | bigint | 越境防止 |
| invoice | `buyer_invoices.partner_id` | `partner_id` | Yes | No | bigint | 同期対象 partner 参照必須 |
| invoice | `buyer_invoices.partner_code` | `partner_code_snapshot` | No | Yes | varchar | 請求時点スナップショット |
| invoice | `buyer_invoices.partner_name` | `partner_name_snapshot` | No | Yes | varchar | 同上 |
| invoice | `buyer_invoices.status` | `invoice_status` | Yes | No | varchar | 変換表で固定 |
| invoice | `buyer_invoices.closing_date` | `closing_date` | Yes | No | date | 運用キー |
| invoice | `buyer_invoices.updated_at` | `source_updated_at` | Yes | No | datetime | checkpoint基準 |

## 3. 整合制約

- `buyers.partner_id` が `partners(id, is_supplier=0)` に一致しない場合は同期対象外に隔離し、`doc_sync_errors` に記録。
- `buyer_invoices.partner_id` が同期対象partnerに紐づかない場合は同期対象外に隔離し、再実行可能なエラーとして記録。
- `client_settings` が欠損する `client_id` は warning として run に記録し、同期本体は継続。

## 4. 検証

- 5テーブル（`clients/partners/buyers/buyer_invoices/client_settings`）の扱いが未定義ゼロ。
- `partners(is_supplier=0)` の source件数と抽出件数が一致。
- 同一入力で dry-run 2回実行時に2回目で新規upsert候補が増加しない。

## 5. ロールバック

- 契約版を `contract_version` で管理し、前版へ切替。
- 新契約で生成された run item / error は監査保持し、書込は feature flag で停止。

## 6. リスク

- 中: 列解釈誤りによる下流欠損。
- 高: `client_id` 境界逸脱による越境反映。
- 中: generated column (`partners.name`) の扱い差異による不要差分。
