<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentChangeRequestsResource\Pages\ListDocumentChangeRequests;
use App\Models\DocumentChangeRequest;
use App\Services\DocumentChangeCommentClient;
use App\Services\DocumentChangeRequestClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class DocumentChangeRequestsResource extends Resource
{
    protected static ?string $model = DocumentChangeRequest::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = '修正依頼';

    protected static string | \UnitEnum | null $navigationGroup = '帳票管理';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('request_uuid')->label('request_uuid')->searchable(),
                Tables\Columns\TextColumn::make('document_id')->label('document_id')->sortable(),
                Tables\Columns\TextColumn::make('invoiceDocument.partner_name')->label('partner_name')->searchable(),
                Tables\Columns\TextColumn::make('invoiceDocument.document_type')->label('document_type')->badge(),
                Tables\Columns\TextColumn::make('requester_partner_id')->label('partner_id')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('依頼日時')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'open',
                        'in_review' => 'in_review',
                        'resolved' => 'resolved',
                        'rejected' => 'rejected',
                        'canceled' => 'canceled',
                    ]),
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('開始日'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('終了日'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Filter::make('partner_name')
                    ->form([
                        TextInput::make('name')->label('取引先名'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['name'] ?? null,
                            fn (Builder $q, $name) => $q->whereHas(
                                'invoiceDocument',
                                fn (Builder $dq) => $dq->where('partner_name', 'like', "%{$name}%")
                            )
                        );
                    }),
                Filter::make('document_type')
                    ->form([
                        Select::make('value')
                            ->label('文書種別')
                            ->options([
                                'invoice' => 'invoice',
                                'delivery_note' => 'delivery_note',
                                'rebate_invoice' => 'rebate_invoice',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $value) => $q->whereHas(
                                'invoiceDocument',
                                fn (Builder $dq) => $dq->where('document_type', $value)
                            )
                        );
                    }),
            ])
            ->actions([
                Action::make('viewDetails')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('修正依頼詳細')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('閉じる')
                    ->form([
                        Textarea::make('requested_payload_json')
                            ->label('requested_payload_json')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('comments')
                            ->label('コメント履歴')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->fillForm(fn (DocumentChangeRequest $record): array => [
                        'requested_payload_json' => static::encodeRequestedPayload($record),
                        'comments' => static::encodeComments($record),
                    ]),
                Action::make('changeStatus')
                    ->label('ステータス変更')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Select::make('status')
                            ->label('status')
                            ->options([
                                'open' => 'open',
                                'in_review' => 'in_review',
                                'resolved' => 'resolved',
                                'rejected' => 'rejected',
                                'canceled' => 'canceled',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn (DocumentChangeRequest $record): array => ['status' => $record->status])
                    ->action(function (DocumentChangeRequest $record, array $data): void {
                        try {
                            $updated = app(DocumentChangeRequestClient::class)->updateStatus((int) $record->id, (string) $data['status']);

                            Notification::make()
                                ->title($updated ? '更新完了' : '対象なし')
                                ->body($updated ? 'ステータスを更新しました。' : '対象レコードが見つかりません。')
                                ->{$updated ? 'success' : 'warning'}()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('ステータス更新失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('addComment')
                    ->label('コメント投稿')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->form([
                        Textarea::make('body')
                            ->label('コメント')
                            ->rows(4)
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(function (DocumentChangeRequest $record, array $data): void {
                        try {
                            app(DocumentChangeCommentClient::class)->post((int) $record->id, (string) $data['body']);

                            Notification::make()
                                ->title('コメント投稿完了')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('コメント投稿失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentChangeRequests::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('invoiceDocument');
    }

    private static function encodeRequestedPayload(DocumentChangeRequest $record): string
    {
        $payload = $record->requested_payload_json;
        if (is_array($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            return is_string($json) ? $json : '{}';
        }

        return (string) ($payload ?? '{}');
    }

    private static function encodeComments(DocumentChangeRequest $record): string
    {
        /** @var Collection<int, array<string, mixed>> $comments */
        $comments = app(DocumentChangeRequestClient::class)->getComments((int) $record->id);

        if ($comments->isEmpty()) {
            return '(コメントなし)';
        }

        return $comments->map(
            fn (array $row): string => sprintf(
                '[%s] %s:%s %s',
                (string) ($row['created_at'] ?? '-'),
                (string) ($row['commenter_type'] ?? '-'),
                (string) ($row['commenter_id'] ?? '-'),
                (string) ($row['body'] ?? '')
            )
        )->implode("\n");
    }
}
