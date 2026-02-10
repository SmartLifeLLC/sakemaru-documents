<?php

namespace App\Filament\Partner\Resources\Documents;

use App\Filament\Partner\Resources\Documents\Pages\CreateDocument;
use App\Filament\Partner\Resources\Documents\Pages\EditDocument;
use App\Filament\Partner\Resources\Documents\Pages\ListDocuments;
use App\Filament\Partner\Resources\Documents\DocumentResource\Pages;
use App\Filament\Partner\Resources\Documents\DocumentResource\RelationManagers;
use App\Models\Document;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('partner_id', Filament::auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Hidden::make('partner_id')
                    ->default(fn () => Filament::auth()->id()),
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
                        'manual' => '手動',
                    ])
                    ->default('manual')
                    ->required(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->label('取引日')
                    ->required(),
                Forms\Components\TextInput::make('partner_name')
                    ->label('取引先名')
                    ->default(fn () => Filament::auth()->user()->name)
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
                Forms\Components\Hidden::make('status')
                    ->default('draft'),
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
