# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
