<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncCheckpointsResource\Pages\ListSyncCheckpoints;
use App\Models\DocSyncCheckpoint;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SyncCheckpointsResource extends Resource
{
    protected static ?string $model = DocSyncCheckpoint::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = '同期チェックポイント';

    protected static string |\UnitEnum | null $navigationGroup = '同期運用';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cursor_updated_at')
                    ->label('Cursor Updated At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cursor_id')
                    ->label('Cursor ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_run_id')
                    ->label('Last Run')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('resetCheckpoint')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'cursor_updated_at' => null,
                            'cursor_id' => 0,
                            'last_run_id' => null,
                            'updated_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncCheckpoints::route('/'),
        ];
    }
}
