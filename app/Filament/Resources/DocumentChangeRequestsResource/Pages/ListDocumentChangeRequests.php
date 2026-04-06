<?php

namespace App\Filament\Resources\DocumentChangeRequestsResource\Pages;

use App\Filament\Resources\DocumentChangeRequestsResource;
use App\Services\DocumentChangeRequestClient;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class ListDocumentChangeRequests extends ListRecords
{
    protected static string $resource = DocumentChangeRequestsResource::class;

    protected function paginateTableQuery(\Illuminate\Database\Eloquent\Builder $query): Paginator|CursorPaginator
    {
        $result = parent::paginateTableQuery($query);

        $ids = collect($result->items())->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids !== []) {
            DocumentChangeRequestsResource::$unreadIds = app(DocumentChangeRequestClient::class)->getUnreadRequestIds($ids);
        }

        return $result;
    }
}
