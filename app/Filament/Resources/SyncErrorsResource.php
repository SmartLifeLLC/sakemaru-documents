<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncErrorsResource\Pages\ListSyncErrors;
use App\Models\DocSyncError;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncErrorsResource extends Resource
{
    protected static ?string $model = DocSyncError::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = '同期エラー';

    protected static string |\UnitEnum | null $navigationGroup = '同期運用';

    protected static ?int $navigationSort = 3;

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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('run_id')
                    ->label('Run')
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_client_id')
                    ->label('Client')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_pk')
                    ->label('Source PK')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stage')
                    ->label('Stage'),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Message')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->error_message),
                Tables\Columns\IconColumn::make('is_retryable')
                    ->label('Retry')
                    ->boolean(),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->options([
                        'partner' => 'partner',
                        'buyer_invoice' => 'buyer_invoice',
                    ]),
                SelectFilter::make('is_retryable')
                    ->options([
                        '1' => 'retryable',
                        '0' => 'non-retryable',
                    ]),
                SelectFilter::make('resolved')
                    ->label('Resolved')
                    ->options([
                        'no' => 'unresolved',
                        'yes' => 'resolved',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if ($value === 'yes') {
                            return $query->whereNotNull('resolved_at');
                        }

                        if ($value === 'no') {
                            return $query->whereNull('resolved_at');
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Actions\Action::make('markResolved')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->resolved_at === null)
                    ->action(function ($record): void {
                        $record->update([
                            'resolved_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncErrors::route('/'),
        ];
    }
}
