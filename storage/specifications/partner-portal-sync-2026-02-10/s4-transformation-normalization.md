# S4: 変換・正規化設計 v1

- ステータス: finalized
- 目的: 同一入力が同一出力になる deterministic 変換

## 変換原則

- 入力NULLは契約定義どおり許容/不許容を厳格適用。
- 文字列は trim、連続空白正規化、空文字は nullable項目のみ NULL 化。
- 日付/日時は UTC で保持（表示時変換）。

## partner 名称

- `partner_name` は `partners.name` を正とする。
- `partner_name_parts` に `name_symbol/name_area/name_main/name_store` を保持。
- company統合候補用に `normalized_name_key`（記号除去・全角半角正規化）を生成。

## status/enum

- `buyer_invoices.status` は target enum に写像。
- 未知値は `unknown` にフォールバックし error を warning 記録。

## 監査キー

- すべての entity で `source_client_id`, `source_pk`, `source_updated_at` を保持。

## 検証

- 同一入力2回で `payload_hash` が一致。
- enum 未知値が失敗停止ではなく隔離記録される。

## ロールバック

- 正規化ルールを v0 に戻し、`normalized_name_key` 生成停止。

## リスク

- 中: 正規化しすぎによる統合候補の誤検知。
- 低: 日時変換の表示差異。
