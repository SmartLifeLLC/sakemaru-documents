<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncRunItemsResource\Pages\ListSyncRunItems;
use App\Models\DocSyncRunItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncRunItemsResource extends Resource
{
    protected static ?string $model = DocSyncRunItem::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = '同期明細';

    protected static string | \UnitEnum | null $navigationGroup = '同期運用';

    protected static ?int $navigationSort = 2;

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
                Tables\Columns\TextColumn::make('run_id')->label('Run')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('entity_type')->label('Entity')->searchable(),
                Tables\Columns\TextColumn::make('source_client_id')->label('Client')->sortable(),
                Tables\Columns\TextColumn::make('source_pk')->label('Source PK')->searchable(),
                Tables\Columns\TextColumn::make('operation')->label('Operation')->badge(),
                Tables\Columns\TextColumn::make('result_status')->label('Result')->badge(),
                Tables\Columns\TextColumn::make('attempt_count')->label('Attempt')->numeric(),
                Tables\Columns\TextColumn::make('processed_at')->label('Processed')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->options([
                        'partner' => 'partner',
                        'buyer_invoice' => 'buyer_invoice',
                    ]),
                SelectFilter::make('result_status')
                    ->options([
                        'success' => 'success',
                        'skipped' => 'skipped',
                        'failed' => 'failed',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncRunItems::route('/'),
        ];
    }
}
