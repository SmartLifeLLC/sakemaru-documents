<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuyerInvoicesResource\Pages\ListBuyerInvoices;
use App\Models\BuyerInvoice;
use App\Services\CustomInvoiceJsonBuilder;
use App\Services\CustomInvoiceQueueClient;
use App\Services\DocumentPublishClient;
use App\Services\ExternalInvoiceClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->paginationPageOptions([10, 25, 50, 100, 'all'])
            ->extraAttributes(['class' => 'buyer-invoices-table sticky-actions'])
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
            ->headerActions([
                Action::make('createCustomInvoice')
                    ->label('新規作成キュー登録')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('新規作成フォーム')
                    ->modalDescription('JSON v1を組み立てて custom_invoice_queue に登録します。')
                    ->form(static::customInvoiceFormSchema(false))
                    ->fillForm(fn (): array => static::defaultCreateFormValues())
                    ->action(function (array $data): void {
                        static::enqueueFromForm('create', $data);
                    }),
                Action::make('checkQueueStatus')
                    ->label('キュー状態確認')
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->color('gray')
                    ->form([
                        TextInput::make('queue_uuid')
                            ->label('queue_uuid')
                            ->required()
                            ->maxLength(191),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $status = app(CustomInvoiceQueueClient::class)->getStatus((string) $data['queue_uuid']);

                            if ($status === null) {
                                Notification::make()
                                    ->title('未検出')
                                    ->body('指定された queue_uuid は存在しません。')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('キュー状態')
                                ->body(static::buildQueueStatusMessage($status))
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('状態取得失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    }),
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
                Action::make('reviseInitialValues')
                    ->label('修正初期値')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->modalHeading('修正フォーム初期値（external_invoices）')
                    ->modalDescription('同一DB external_invoices から取得した結果です。')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('閉じる')
                    ->form([
                        TextInput::make('source_uuid')->label('source_uuid')->disabled()->dehydrated(false),
                        TextInput::make('partner_name')->label('取引先名')->disabled()->dehydrated(false),
                        TextInput::make('partner_code')->label('取引先コード')->disabled()->dehydrated(false),
                        TextInput::make('invoice_number')->label('請求書番号')->disabled()->dehydrated(false),
                        TextInput::make('closing_date')->label('締日')->disabled()->dehydrated(false),
                        TextInput::make('billing_amount')->label('請求金額')->disabled()->dehydrated(false),
                        TextInput::make('print_type')->label('print_type')->disabled()->dehydrated(false),
                        TextInput::make('branch')->label('branch')->disabled()->dehydrated(false),
                        TextInput::make('salesman')->label('salesman')->disabled()->dehydrated(false),
                        Textarea::make('summary_json')
                            ->label('summary JSON')
                            ->rows(6)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('metadata_json')
                            ->label('metadata JSON')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Placeholder::make('api_note')
                            ->label('注記')
                            ->content('P2でこの初期値を実フォームにマッピングします。')
                            ->columnSpanFull(),
                    ])
                    ->fillForm(fn (BuyerInvoice $record): array => static::buildReviseInitialValues((string) $record->uuid)),
                Action::make('reviseCustomInvoice')
                    ->label('修正キュー登録')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->modalHeading('修正フォーム')
                    ->modalDescription('修正内容を JSON v1 で組み立てて custom_invoice_queue へ登録します。')
                    ->form(static::customInvoiceFormSchema(true))
                    ->fillForm(fn (BuyerInvoice $record): array => static::defaultReviseFormValues((string) $record->uuid))
                    ->action(function (BuyerInvoice $record, array $data): void {
                        $data['source_uuid'] = (string) $record->uuid;
                        static::enqueueFromForm('revise', $data);
                    }),
                Action::make('previewPublish')
                    ->label('公開プレビュー')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('公開同期プレビュー')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('閉じる')
                    ->form([
                        TextInput::make('source_uuid')->label('source_uuid')->disabled()->dehydrated(false),
                        TextInput::make('target_table')->label('target_table')->disabled()->dehydrated(false),
                        Textarea::make('payload_json')->label('upsert payload')->rows(12)->disabled()->dehydrated(false)->columnSpanFull(),
                    ])
                    ->fillForm(function (BuyerInvoice $record): array {
                        try {
                            $preview = app(DocumentPublishClient::class)->buildPreview((string) $record->uuid);

                            return [
                                'source_uuid' => (string) $record->uuid,
                                'target_table' => (string) $preview['table'],
                                'payload_json' => static::encodeJsonForDisplay((array) ($preview['payload'] ?? [])),
                            ];
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('プレビュー失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();

                            return [
                                'source_uuid' => (string) $record->uuid,
                                'target_table' => '-',
                                'payload_json' => '{}',
                            ];
                        }
                    }),
                Action::make('publishToInvoice')
                    ->label('公開')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('queue_uuid')
                            ->label('queue_uuid（succeeded確認用）')
                            ->required()
                            ->maxLength(191),
                    ])
                    ->requiresConfirmation()
                    ->action(function (BuyerInvoice $record, array $data): void {
                        try {
                            $status = app(CustomInvoiceQueueClient::class)->getStatus((string) $data['queue_uuid']);
                            if ($status === null) {
                                Notification::make()
                                    ->title('公開中止')
                                    ->body('queue_uuid が見つかりません。')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $state = (string) ($status['status'] ?? '');
                            if ($state !== 'succeeded') {
                                Notification::make()
                                    ->title('公開中止')
                                    ->body("queue status が succeeded ではありません（現在: {$state}）。")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            app(DocumentPublishClient::class)->publish((string) $record->uuid);

                            Notification::make()
                                ->title('公開同期完了')
                                ->body("uuid={$record->uuid} を invoice documents に同期しました（queue={$data['queue_uuid']}）。")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('公開同期失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('unpublishFromInvoice')
                    ->label('公開取り消し')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (BuyerInvoice $record): void {
                        try {
                            $updated = app(DocumentPublishClient::class)->unpublish((string) $record->uuid);

                            Notification::make()
                                ->title($updated ? '公開取り消し完了' : '対象なし')
                                ->body($updated ? "uuid={$record->uuid} を非公開化しました。" : '対象レコードが見つかりません。')
                                ->{$updated ? 'success' : 'warning'}()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('公開取り消し失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
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

    /**
     * @return array<string, string>
     */
    private static function buildReviseInitialValues(string $uuid): array
    {
        $empty = [
            'source_uuid' => $uuid,
            'partner_name' => '-',
            'partner_code' => '-',
            'invoice_number' => '-',
            'closing_date' => '-',
            'billing_amount' => '-',
            'print_type' => '-',
            'branch' => '-',
            'salesman' => '-',
            'summary_json' => '{}',
            'metadata_json' => '{}',
        ];

        try {
            $source = app(ExternalInvoiceClient::class)->getByUuid($uuid);

            if ($source === null) {
                Notification::make()
                    ->title('初期値取得失敗')
                    ->body("uuid={$uuid} の文書が external_invoices に存在しません。")
                    ->warning()
                    ->send();

                return $empty;
            }

            $metadata = is_array($source['metadata'] ?? null) ? $source['metadata'] : [];
            $summary = is_array($metadata['summary'] ?? null) ? $metadata['summary'] : [];
            $branch = is_array($metadata['branch'] ?? null) ? $metadata['branch'] : [];
            $salesman = is_array($metadata['salesman'] ?? null) ? $metadata['salesman'] : [];

            return [
                'source_uuid' => (string) ($source['uuid'] ?? $uuid),
                'partner_name' => (string) ($source['partner_name'] ?? '-'),
                'partner_code' => (string) ($source['partner_code'] ?? '-'),
                'invoice_number' => (string) ($source['invoice_number'] ?? '-'),
                'closing_date' => (string) ($source['closing_date'] ?? '-'),
                'billing_amount' => isset($source['billing_amount']) ? (string) $source['billing_amount'] : '-',
                'print_type' => (string) ($source['print_type'] ?? '-'),
                'branch' => trim(((string) ($branch['code'] ?? '')).' '.((string) ($branch['name'] ?? ''))) ?: '-',
                'salesman' => trim(((string) ($salesman['code'] ?? '')).' '.((string) ($salesman['name'] ?? ''))) ?: '-',
                'summary_json' => static::encodeJsonForDisplay($summary),
                'metadata_json' => static::encodeJsonForDisplay($metadata),
            ];
        } catch (Throwable $e) {
            Notification::make()
                ->title('初期値取得失敗')
                ->body(mb_substr($e->getMessage(), 0, 500))
                ->danger()
                ->send();

            return $empty;
        }
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function customInvoiceFormSchema(bool $revise): array
    {
        $schema = [
            Hidden::make('request_uuid')
                ->default(fn (): string => (string) Str::uuid()),
            Select::make('document_type')
                ->label('文書種別')
                ->options([
                    'invoice' => '請求書',
                    'delivery_note' => '納品書',
                    'rebate_invoice' => 'リベート請求書',
                ])
                ->required(),
            TextInput::make('client_id')
                ->label('client_id')
                ->numeric()
                ->required(),
            TextInput::make('partner_id')
                ->label('partner_id')
                ->numeric()
                ->required(),
            DatePicker::make('issue_date')
                ->label('発行日')
                ->required(),
            DatePicker::make('closing_date')
                ->label('締日'),
            DatePicker::make('due_date')
                ->label('支払期日'),
            TextInput::make('title')
                ->label('タイトル')
                ->required()
                ->maxLength(255),
            Textarea::make('remarks')
                ->label('備考')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('partner_name')
                ->label('取引先名')
                ->required()
                ->maxLength(255),
            TextInput::make('partner_code')
                ->label('取引先コード')
                ->maxLength(255),
            TextInput::make('billing_email')
                ->label('請求先メール')
                ->email()
                ->maxLength(255),
            Repeater::make('lines')
                ->label('明細')
                ->default(static::defaultLines())
                ->schema([
                    TextInput::make('item_code')->label('品目コード')->maxLength(255),
                    TextInput::make('item_name')->label('品目名')->required()->maxLength(255),
                    TextInput::make('quantity')->label('数量')->numeric()->required(),
                    TextInput::make('unit')->label('単位')->maxLength(50),
                    TextInput::make('unit_price')->label('単価')->numeric()->required(),
                    TextInput::make('amount')->label('金額')->numeric(),
                    Select::make('tax_rate')->label('税率')->options(['8' => '8', '10' => '10'])->default('10')->required(),
                    TextInput::make('tax_amount')->label('税額')->numeric(),
                    TextInput::make('note')->label('備考')->maxLength(255),
                ])
                ->addActionLabel('明細行を追加')
                ->columnSpanFull()
                ->minItems(1),
        ];

        if ($revise) {
            array_splice($schema, 1, 0, [
                TextInput::make('source_uuid')
                    ->label('source_uuid')
                    ->required()
                    ->readOnly(),
            ]);
        }

        return $schema;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function defaultLines(): array
    {
        return [
            [
                'item_code' => null,
                'item_name' => '調整',
                'quantity' => 1,
                'unit' => null,
                'unit_price' => 0,
                'amount' => 0,
                'tax_rate' => '10',
                'tax_amount' => 0,
                'note' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultCreateFormValues(): array
    {
        return [
            'request_uuid' => (string) Str::uuid(),
            'document_type' => 'invoice',
            'client_id' => 1,
            'partner_id' => null,
            'issue_date' => now()->toDateString(),
            'closing_date' => null,
            'due_date' => null,
            'title' => '',
            'remarks' => '',
            'partner_name' => '',
            'partner_code' => '',
            'billing_email' => null,
            'lines' => static::defaultLines(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultReviseFormValues(string $sourceUuid): array
    {
        $source = app(ExternalInvoiceClient::class)->getByUuid($sourceUuid);
        if ($source === null) {
            return array_merge(static::defaultCreateFormValues(), [
                'source_uuid' => $sourceUuid,
            ]);
        }

        return [
            'request_uuid' => (string) Str::uuid(),
            'source_uuid' => $sourceUuid,
            'document_type' => static::resolveDocumentTypeFromSource($source),
            'client_id' => $source['client_id'] ?? 1,
            'partner_id' => $source['partner_id'] ?? null,
            'issue_date' => now()->toDateString(),
            'closing_date' => $source['closing_date'] ?? null,
            'due_date' => null,
            'title' => (($source['invoice_number'] ?? '') !== '' ? '修正:'.$source['invoice_number'] : '修正依頼'),
            'remarks' => '',
            'partner_name' => $source['partner_name'] ?? '',
            'partner_code' => $source['partner_code'] ?? '',
            'billing_email' => null,
            'lines' => [
                [
                    'item_code' => null,
                    'item_name' => '修正項目',
                    'quantity' => 1,
                    'unit' => null,
                    'unit_price' => (int) ($source['billing_amount'] ?? 0),
                    'amount' => (int) ($source['billing_amount'] ?? 0),
                    'tax_rate' => '10',
                    'tax_amount' => (int) round(((int) ($source['billing_amount'] ?? 0)) * 0.1),
                    'note' => '',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private static function enqueueFromForm(string $requestType, array $formData): void
    {
        try {
            $json = app(CustomInvoiceJsonBuilder::class)->build($requestType, $formData, auth('web')->id() ?? auth()->id());
            $queueUuid = app(CustomInvoiceQueueClient::class)->enqueue(
                $json,
                $requestType,
                (string) ($formData['document_type'] ?? ''),
                (string) ($formData['request_uuid'] ?? '')
            );

            Notification::make()
                ->title('キュー登録完了')
                ->body("queue_uuid={$queueUuid}")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('キュー登録失敗')
                ->body(mb_substr($e->getMessage(), 0, 500))
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private static function buildQueueStatusMessage(array $status): string
    {
        $queueUuid = (string) ($status['queue_uuid'] ?? '-');
        $state = (string) ($status['status'] ?? '-');
        $error = trim((string) ($status['error_message'] ?? ''));

        if ($error === '') {
            return "queue_uuid={$queueUuid} / status={$state}";
        }

        return "queue_uuid={$queueUuid} / status={$state} / error={$error}";
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function resolveDocumentTypeFromSource(array $source): string
    {
        $documentType = trim((string) ($source['document_type'] ?? ''));
        if (in_array($documentType, ['invoice', 'delivery_note', 'rebate_invoice'], true)) {
            return $documentType;
        }

        return 'invoice';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encodeJsonForDisplay(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return is_string($json) ? $json : '{}';
    }
}
