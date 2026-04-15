# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 絶対禁止事項（CRITICAL）

### データベース破壊コマンドの禁止

```bash
php artisan migrate:fresh      # 禁止
php artisan migrate:refresh    # 禁止
php artisan migrate:reset      # 禁止
php artisan db:wipe            # 禁止
```

---

## 重要: UIデザイン仕様（プロジェクト横断共通）

以下のデザイン仕様書は酒丸シリーズ全プロジェクト共通。該当UIの作成・修正時に必ず参照すること。

1. **モーダルデザイン仕様**: `~/.claude/design-knowledge/modal-design.md`
   - ヘッダー紺色（#1e293b）、ボタン右寄せ、実行ボタン赤（danger）
   - キャンセルは「〜せず閉じる」形式
   - CSSクラス: `incoming-detail-modal`（theme.css で定義済み）

2. **メガメニュー仕様**: `~/.claude/design-knowledge/mega-menu.md`
   - ヘッダー `bg-slate-800`、高さ 2.5rem、z-[35]
   - 動的カラムレイアウト（1〜3列）、Split View 連携

3. **テーブルタブ表示仕様**: `~/.claude/design-knowledge/table-tabs.md`（プロジェクト横断共通）
   - 4パターン: getTabs() / PresetView / Form Schema Tabs / Sub-Navigation Tabs
   - パターン選択ガイド・実装例・動的タブ生成・キャッシュ戦略
   - テーブル固定高さ + 内部スクロール + sticky thead

4. **ページスクロール制御仕様**: `~/.claude/design-knowledge/page-scroll-control.md`
   - HTML overflow 制御、sticky カラム（右固定/左固定）
   - Split View（左右分割パネル + ドラッグリサイズ）

5. **テーブルコンパクトデザイン仕様**: `~/.claude/design-knowledge/table-compact-design.md`（プロジェクト横断共通）
   - 行コンパクト化、ページヘッダー余白、sticky-actions右固定、ストライプ行
   - TextInputColumn幅固定、トップバー高さ調整

### Filament 4 の注意事項

```php
use Filament\Schemas\Components\Section;      // NOT Filament\Forms\Components\Section
use Filament\Schemas\Components\Grid;         // NOT Filament\Infolists\Components\Grid
use Filament\Actions\Action;                  // NOT Filament\Tables\Actions\Action
```

---

## Project Overview

Sakemaru Documents Management System - A Laravel 12 + Filament 4 application for managing documents (invoices) with multi-tenant support and partner management. Uses SQLite for local database and connects to an external 'sakemaru' MySQL database for partner data.

## Development Commands

```bash
# Initial setup
composer setup          # Install deps, generate keys, migrate, build frontend

# Development (runs Laravel server, queue listener, logs, and Vite concurrently)
composer dev

# Testing
composer test           # Clear config and run PHPUnit tests

# Deep cache clearing
php artisan cache:hard-clear

# Frontend only
npm run dev             # Vite dev server with hot reload
npm run build           # Production build
```

## Architecture

### Multi-Database Setup
- **Default (SQLite):** Local app database for users, documents, audit logs
- **Sakemaru (MySQL):** External database for Partners (supplier/customer master data)

### Filament Admin Panels
- **Admin Panel (`/admin`):** System administrators manage all documents and users
- **Partner Portal (`/partner`):** Partners manage their own documents

### Key Services
- **AuditLogService:** Tracks user actions (login, document operations) with IP/user agent
- **InvoiceFileUploadService:** S3 uploads with path structure `documents/{client_id}/{owner_dir}/{year}/{month}/{category}_{ymd}_{partner_name}_{amount}_{uuid}.{ext}`, includes SHA256 hashing

### Model Relationships
- **User:** Authenticatable, soft-deletable, belongs to Partner, has documents via partner_id
- **Partner:** External model (sakemaru connection), is_supplier boolean, has many users
- **Document:** category (issued/received), source_type (system/manual), transaction_date, amount, s3_path, status (draft/published/archived), hash

### Event Listeners
- **LogSuccessfulLogin:** Logs authentication events via AuditLogService

## Configuration Notes

- Timezone: Asia/Tokyo
- PHP 8.2+
- Filament resources in `app/Filament/Resources/` with schemas in subdirectories
- Partner-specific resources in `app/Filament/Partner/Resources/`
- Custom login at `app/Filament/Pages/Auth/CustomLogin.php`

## Testing

Tests use in-memory SQLite. Run with `composer test` or `php artisan test`.
