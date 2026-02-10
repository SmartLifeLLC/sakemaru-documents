<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuyerInvoicesResource\Pages\ListBuyerInvoices;
use App\Models\BuyerInvoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BuyerInvoicesResource extends Resource
{
    protected static ?string $model = BuyerInvoice::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '請求書（酒丸）';

    protected static string | \UnitEnum | null $navigationGroup = '帳票管理';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('client_id')->label('Client')->sortable(),
                Tables\Columns\TextColumn::make('partner_name')->label('取引先名')->searchable(),
                Tables\Columns\TextColumn::make('invoice_number')->label('請求書番号')->searchable(),
                Tables\Columns\TextColumn::make('closing_date')->label('締日')->date()->sortable(),
                Tables\Columns\TextColumn::make('billing_amount')->label('請求金額')->money('JPY')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('ステータス')->badge()->searchable(),
                Tables\Columns\TextColumn::make('is_active')->label('有効')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '有効' : '無効'),
                Tables\Columns\TextColumn::make('updated_at')->label('更新日時')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(fn (): array => static::statusOptions()),
                SelectFilter::make('is_active')->options([
                    '1' => '有効',
                    '0' => '無効',
                ]),
            ])
            ->actions([
                Action::make('changeStatus')
                    ->label('ステータス変更')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Select::make('status')
                            ->label('新しいステータス')
                            ->options(fn (): array => static::statusOptions())
                            ->required(),
                    ])
                    ->fillForm(fn (BuyerInvoice $record): array => [
                        'status' => $record->status,
                    ])
                    ->action(function (BuyerInvoice $record, array $data): void {
                        $newStatus = (string) $data['status'];

                        if ($record->status === $newStatus) {
                            Notification::make()
                                ->title('変更なし')
                                ->body('ステータスは既に同じ値です。')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => $newStatus,
                            'updated_at' => now(),
                        ]);

                        Notification::make()
                            ->title('更新完了')
                            ->body("ステータスを {$newStatus} に更新しました。")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuyerInvoices::route('/'),
        ];
    }

    private static function statusOptions(): array
    {
        $base = [
            'pending' => 'pending',
            'canceled' => 'canceled',
        ];

        $fromDb = BuyerInvoice::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter(fn ($status): bool => is_string($status) && $status !== '')
            ->mapWithKeys(fn (string $status): array => [$status => $status])
            ->all();

        return $fromDb + $base;
    }
}
