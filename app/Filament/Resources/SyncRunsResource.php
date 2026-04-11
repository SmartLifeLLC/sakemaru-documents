<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncRunsResource\Pages\ListSyncRuns;
use App\Models\DocSyncRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncRunsResource extends Resource
{
    protected static ?string $model = DocSyncRun::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = '同期実行';

    protected static string |\UnitEnum | null $navigationGroup = '同期運用';

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
                Tables\Columns\TextColumn::make('id')
                    ->label('Run ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mode')
                    ->badge()
                    ->label('Mode'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status'),
                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->sortable(),
                Tables\Columns\TextColumn::make('scanned_count')
                    ->label('Scanned')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('upsert_count')
                    ->label('Upsert')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('skip_count')
                    ->label('Skip')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_count')
                    ->label('Error')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Start')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Finish')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('mode')
                    ->options([
                        'dry_run' => 'dry_run',
                        'apply' => 'apply',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'running' => 'running',
                        'success' => 'success',
                        'partial' => 'partial',
                        'failed' => 'failed',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncRuns::route('/'),
        ];
    }
}
