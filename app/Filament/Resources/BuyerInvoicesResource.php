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
use Illuminate\Support\Facades\Storage;
use Throwable;

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
                Action::make('download')
                    ->label('ダウンロード')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (BuyerInvoice $record): bool => filled($record->s3_bucket) && filled($record->s3_path))
                    ->url(function (BuyerInvoice $record): ?string {
                        try {
                            return static::buildDownloadUrl($record);
                        } catch (Throwable) {
                            return static::buildPublicDownloadUrl($record);
                        }
                    })
                    ->openUrlInNewTab(),
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

    private static function buildDownloadUrl(BuyerInvoice $record): string
    {
        $rawPath = ltrim((string) $record->s3_path, '/');
        $bucket = (string) $record->s3_bucket;
        $fileName = trim((string) ($record->file_name ?: basename($rawPath)));
        $fallbackName = $fileName !== '' ? $fileName : 'invoice.pdf';
        $encodedName = rawurlencode($fallbackName);

        return Storage::disk('s3')->temporaryUrl(
            $rawPath,
            now()->addMinutes(10),
            [
                'Bucket' => $bucket,
                'ResponseContentDisposition' => "attachment; filename=\"invoice.pdf\"; filename*=UTF-8''{$encodedName}",
            ],
        );
    }

    private static function buildPublicDownloadUrl(BuyerInvoice $record): string
    {
        $path = static::encodeS3Path((string) $record->s3_path);
        $bucket = (string) $record->s3_bucket;
        $fileName = trim((string) ($record->file_name ?: basename($path)));
        $fallbackName = $fileName !== '' ? $fileName : 'invoice.pdf';
        $encodedName = rawurlencode($fallbackName);

        $baseUrl = trim((string) config('filesystems.disks.s3.url', ''));
        $endpoint = trim((string) config('filesystems.disks.s3.endpoint', ''));
        $region = trim((string) config('filesystems.disks.s3.region', 'ap-northeast-1'));

        if ($baseUrl !== '') {
            $url = rtrim($baseUrl, '/').'/'.$path;
        } elseif ($endpoint !== '') {
            $endpoint = rtrim($endpoint, '/');
            $url = str_contains($endpoint, $bucket)
                ? "{$endpoint}/{$path}"
                : "{$endpoint}/{$bucket}/{$path}";
        } else {
            $url = $region !== ''
                ? "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}"
                : "https://{$bucket}.s3.amazonaws.com/{$path}";
        }

        return $url.'?response-content-disposition='.rawurlencode("attachment; filename=\"invoice.pdf\"; filename*=UTF-8''{$encodedName}");
    }

    private static function encodeS3Path(string $path): string
    {
        $segments = array_filter(explode('/', ltrim($path, '/')), fn (string $segment): bool => $segment !== '');

        return implode('/', array_map(static fn (string $segment): string => rawurlencode($segment), $segments));
    }
}
