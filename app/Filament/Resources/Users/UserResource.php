<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource\Pages;
use App\Filament\Resources\Users\UserResource\RelationManagers;
use App\Models\Partner;
use App\Services\InvoiceOnboardingStartService;
use App\Services\InvoiceOnboardingStatusService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class UserResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = '取引先アカウント';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('code')
                    ->label('取引先CD')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('取引先名')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('メールアドレス')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->maxLength(255)
                    ->hiddenOn('edit'),
                Forms\Components\Toggle::make('is_supplier')
                    ->label('仕入先')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('有効')
                    ->required(),
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
                Tables\Columns\TextColumn::make('code')
                    ->label('取引先CD')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('取引先名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice_onboarding_status')
                    ->label('連携状態')
                    ->badge()
                    ->state(function (Partner $record): string {
                        $status = app(InvoiceOnboardingStatusService::class)->latestForPartner($record)['status'] ?? null;

                        return $status ?? '未連携';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'activated' => 'success',
                        'failed', 'expired' => 'danger',
                        'sent', 'processing' => 'info',
                        'requested' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('invoice_onboarding_email')
                    ->label('連携メール')
                    ->state(function (Partner $record): string {
                        $email = app(InvoiceOnboardingStatusService::class)->latestForPartner($record)['initial_email'] ?? null;

                        return $email ?? '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_onboarding_requested_at')
                    ->label('連携依頼日時')
                    ->state(function (Partner $record): ?string {
                        return app(InvoiceOnboardingStatusService::class)->latestForPartner($record)['requested_at'] ?? null;
                    })
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_supplier')
                    ->label('仕入先')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\Action::make('startInvoiceOnboarding')
                    ->label('連携開始')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('initial_email')
                            ->label('初期メールアドレス')
                            ->email()
                            ->default(fn (Partner $record): ?string => $record->email)
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Partner $record, array $data): void {
                        try {
                            $result = app(InvoiceOnboardingStartService::class)->start(
                                partner: $record,
                                initialEmail: (string) $data['initial_email'],
                                requestedBy: auth('web')->id(),
                            );

                            Notification::make()
                                ->title('連携リクエストを登録しました')
                                ->body("status={$result['status']} / dedupe={$result['dedupe_key']}")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('連携開始に失敗しました')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Partner $record): bool => ! (bool) $record->is_supplier),
                Actions\Action::make('resendInvoiceOnboarding')
                    ->label('連携再送')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('initial_email')
                            ->label('再送先メールアドレス')
                            ->email()
                            ->default(function (Partner $record): ?string {
                                $latestEmail = app(InvoiceOnboardingStatusService::class)->latestForPartner($record)['initial_email'] ?? null;

                                return $latestEmail ?? $record->email;
                            })
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Partner $record, array $data): void {
                        try {
                            $result = app(InvoiceOnboardingStartService::class)->start(
                                partner: $record,
                                initialEmail: (string) $data['initial_email'],
                                requestedBy: auth('web')->id(),
                            );

                            Notification::make()
                                ->title('連携再送リクエストを登録しました')
                                ->body("status={$result['status']} / dedupe={$result['dedupe_key']}")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('連携再送に失敗しました')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(function (Partner $record): bool {
                        if ((bool) $record->is_supplier) {
                            return false;
                        }

                        return app(InvoiceOnboardingStatusService::class)->latestForPartner($record) !== null;
                    }),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

}
