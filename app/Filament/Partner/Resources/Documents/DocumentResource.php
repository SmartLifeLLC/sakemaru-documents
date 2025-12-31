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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BackedEnum;
use Illuminate\Support\Facades\Auth;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('partner_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Hidden::make('partner_id')
                    ->default(fn () => Auth::id()),
                Forms\Components\Select::make('category')
                    ->options([
                        'issued' => 'Issued',
                        'received' => 'Received',
                    ])
                    ->required(),
                Forms\Components\Select::make('source_type')
                    ->options([
                        'manual' => 'Manual',
                    ])
                    ->default('manual')
                    ->required(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->required(),
                Forms\Components\TextInput::make('partner_name')
                    ->default(fn () => Auth::user()->name)
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\FileUpload::make('s3_path')
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
            ->columns([
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('JPY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
