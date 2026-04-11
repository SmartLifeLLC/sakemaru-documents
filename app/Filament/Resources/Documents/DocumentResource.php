<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\DocumentResource\Pages;
use App\Filament\Resources\Documents\DocumentResource\RelationManagers;
use App\Models\Document;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = '帳票管理';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('category')
                    ->label('種別')
                    ->options([
                        'issued' => '発行',
                        'received' => '受領',
                    ])
                    ->required(),
                Forms\Components\Select::make('source_type')
                    ->label('登録種別')
                    ->options([
                        'system' => 'システム',
                        'manual' => '手動',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->label('取引日')
                    ->required(),
                Forms\Components\TextInput::make('partner_name')
                    ->label('取引先名')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('金額')
                    ->required()
                    ->numeric(),
                Forms\Components\FileUpload::make('s3_path')
                    ->label('書類')
                    ->disk('s3')
                    ->directory('documents')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('ステータス')
                    ->options([
                        'draft' => '下書き',
                        'published' => '公開',
                        'archived' => '保管',
                    ])
                    ->required(),
                Forms\Components\Select::make('partner_id')
                    ->label('取引先')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100, 'all'])
            ->extraAttributes(['class' => 'my-table sticky-actions'])
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('種別'),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('取引日')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner_name')
                    ->label('取引先名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('金額')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('ステータス')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('from')->label('開始日'),
                        DatePicker::make('to')->label('終了日'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    }),
                Filter::make('amount')
                    ->form([
                        TextInput::make('min')->label('最小金額')->numeric(),
                        TextInput::make('max')->label('最大金額')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'] ?? null, fn (Builder $q, $min) => $q->where('amount', '>=', $min))
                            ->when($data['max'] ?? null, fn (Builder $q, $max) => $q->where('amount', '<=', $max));
                    }),
                Filter::make('partner_name')
                    ->form([
                        TextInput::make('name')->label('取引先名'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['name'] ?? null, fn (Builder $q, $name) => $q->where('partner_name', 'like', "%{$name}%"));
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
