<?php

namespace App\Filament\Resources\DocumentChangeRequestsResource\Pages;

use App\Filament\Resources\DocumentChangeRequestsResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentChangeRequest extends ViewRecord
{
    protected static string $resource = DocumentChangeRequestsResource::class;

    protected string $view = 'filament.resources.document-change-requests.view';

    public function getTitle(): string
    {
        return '修正依頼詳細';
    }

    public function getBreadcrumbs(): array
    {
        return [
            DocumentChangeRequestsResource::getUrl() => '修正依頼',
            '#' => '詳細',
        ];
    }

    public function getStatusColor(): string
    {
        return match ($this->record->status) {
            'open' => '#f59e0b',
            'in_review' => '#3b82f6',
            'resolved' => '#10b981',
            'rejected' => '#ef4444',
            'canceled' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->record->status) {
            'open' => '受付',
            'in_review' => '確認中',
            'resolved' => '完了',
            'rejected' => '却下',
            'canceled' => '取消',
            default => $this->record->status ?? '-',
        };
    }

    public function getRequestTypeLabel(): string
    {
        return match ($this->record->request_type) {
            'name_change' => '宛名変更',
            'address_change' => '住所変更',
            'amount_error' => '金額誤り',
            'remark_addition' => '備考追加',
            'other' => 'その他',
            default => $this->record->request_type ?? '-',
        };
    }
}
