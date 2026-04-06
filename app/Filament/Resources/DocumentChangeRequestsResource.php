<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentChangeRequestsResource\Pages\ListDocumentChangeRequests;
use App\Filament\Resources\DocumentChangeRequestsResource\Pages\ViewDocumentChangeRequest;
use App\Models\DocumentChangeRequest;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentChangeRequestsResource extends Resource
{
    /** @var list<int> */
    public static array $unreadIds = [];

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
                Tables\Columns\IconColumn::make('unread')
                    ->label('')
                    ->getStateUsing(fn (DocumentChangeRequest $record): bool => in_array((int) $record->id, static::$unreadIds ?? [], true))
                    ->boolean()
                    ->trueIcon('heroicon-s-envelope')
                    ->falseIcon('heroicon-o-envelope-open')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('invoiceDocument.partner_name')->label('取引先名')->searchable(),
                Tables\Columns\TextColumn::make('request_type')->label('依頼種別')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'name_change' => '宛名変更',
                        'address_change' => '住所変更',
                        'amount_error' => '金額誤り',
                        'remark_addition' => '備考追加',
                        'other' => 'その他',
                        default => $state ?? '-',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')->label('依頼内容')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('invoiceDocument.document_type')->label('文書種別')->badge(),
                Tables\Columns\TextColumn::make('status')->label('ステータス')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'in_review' => 'info',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        'canceled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('依頼日時')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('最終更新')->dateTime()->sortable(),
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
            ->recordUrl(fn (DocumentChangeRequest $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('viewDetails')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->url(fn (DocumentChangeRequest $record): string => static::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentChangeRequests::route('/'),
            'view' => ViewDocumentChangeRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('invoiceDocument');
    }

}
