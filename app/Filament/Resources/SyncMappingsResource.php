<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncMappingsResource\Pages\ListSyncMappings;
use App\Models\DocSyncMapping;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncMappingsResource extends Resource
{
    protected static ?string $model = DocSyncMapping::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = '同期マッピング';

    protected static string | \UnitEnum | null $navigationGroup = '同期運用';

    protected static ?int $navigationSort = 5;

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
                Tables\Columns\TextColumn::make('entity_type')->label('Entity')->searchable(),
                Tables\Columns\TextColumn::make('source_client_id')->label('Client')->sortable(),
                Tables\Columns\TextColumn::make('source_id')->label('Source ID')->searchable(),
                Tables\Columns\TextColumn::make('source_code')->label('Source Code')->searchable(),
                Tables\Columns\TextColumn::make('target_system')->label('Target')->badge(),
                Tables\Columns\TextColumn::make('target_id')->label('Target ID')->searchable(),
                Tables\Columns\TextColumn::make('mapping_status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('confidence')->label('Confidence')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('last_synced_at')->label('Last Synced')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('stale_at')->label('Stale At')->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->options([
                        'partner' => 'partner',
                        'buyer_invoice' => 'buyer_invoice',
                    ]),
                SelectFilter::make('mapping_status')
                    ->options([
                        'active' => 'active',
                        'stale' => 'stale',
                        'conflict' => 'conflict',
                        'manual_review' => 'manual_review',
                    ]),
                SelectFilter::make('target_system')
                    ->options([
                        'invoice' => 'invoice',
                    ]),
            ])
            ->actions([
                Actions\Action::make('markStale')
                    ->label('Mark Stale')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn ($record) => $record->mapping_status !== 'stale')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'mapping_status' => 'stale',
                            'stale_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }),
                Actions\Action::make('markActive')
                    ->label('Mark Active')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->mapping_status !== 'active')
                    ->action(function ($record): void {
                        $record->update([
                            'mapping_status' => 'active',
                            'stale_at' => null,
                            'updated_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncMappings::route('/'),
        ];
    }
}
