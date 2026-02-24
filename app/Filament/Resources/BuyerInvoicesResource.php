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
use Filament\Actions\BulkAction;
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
    /** @var array<string, array<string, mixed>|null> */
    private static array $latestQueueCache = [];
    /** @var array<string, bool> */
    private static array $publishedStateCache = [];

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
                Tables\Columns\IconColumn::make('published_to_invoice')
                    ->label('公開')
                    ->boolean()
                    ->state(fn (BuyerInvoice $record): bool => static::isPublishedToInvoice($record)),
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
            ->headerActions([])
            ->actions([
                Action::make('download')
                    ->label('PDF確認')
                    ->icon('heroicon-o-eye')
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
                Action::make('publishToInvoice')
                    ->label('公開')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (BuyerInvoice $record): bool => $record->status !== 'canceled')
                    ->action(function (BuyerInvoice $record): void {
                        try {
                            if (static::isPublishedToInvoice($record)) {
                                Notification::make()
                                    ->title('公開済み')
                                    ->body('この請求書は既に公開されています。')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            app(DocumentPublishClient::class)->publish((string) $record->uuid);
                            static::rememberPublishedState((string) $record->uuid, true);

                            Notification::make()
                                ->title('公開同期完了')
                                ->body("uuid={$record->uuid} を invoice documents に同期しました。")
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
                Action::make('cancelInvoice')
                    ->label('キャンセル')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (BuyerInvoice $record): bool => $record->status !== 'canceled')
                    ->action(function (BuyerInvoice $record): void {
                        try {
                            $unpublished = app(DocumentPublishClient::class)->unpublish((string) $record->uuid);
                            static::rememberPublishedState((string) $record->uuid, false);
                            $record->update([
                                'status' => 'canceled',
                                'updated_at' => now(),
                            ]);

                            Notification::make()
                                ->title('キャンセル完了')
                                ->body($unpublished
                                    ? "uuid={$record->uuid} をキャンセルし、公開も取り消しました。"
                                    : "uuid={$record->uuid} をキャンセルしました（公開データは未作成）。")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('キャンセル失敗')
                                ->body(mb_substr($e->getMessage(), 0, 500))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulkPublishToInvoice')
                    ->label('選択した請求書を公開')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $published = 0;
                        $skipped = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            if (! $record instanceof BuyerInvoice) {
                                continue;
                            }

                            try {
                                if ((string) $record->status === 'canceled') {
                                    $skipped++;

                                    continue;
                                }

                                if (static::isPublishedToInvoice($record)) {
                                    $skipped++;

                                    continue;
                                }

                                app(DocumentPublishClient::class)->publish((string) $record->uuid);
                                static::rememberPublishedState((string) $record->uuid, true);
                                $published++;
                            } catch (Throwable) {
                                $failed++;
                            }
                        }

                        Notification::make()
                            ->title('一括公開結果')
                            ->body("公開={$published}件 / スキップ={$skipped}件 / 失敗={$failed}件")
                            ->{$failed > 0 ? 'warning' : 'success'}()
                            ->send();
                    }),
            ]);
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
     * @return array<string, mixed>|null
     */
    private static function findLatestQueueForRecord(BuyerInvoice $record): ?array
    {
        $sourceUuid = trim((string) ($record->uuid ?? ''));

        if ($sourceUuid === '') {
            return null;
        }

        if (array_key_exists($sourceUuid, static::$latestQueueCache)) {
            return static::$latestQueueCache[$sourceUuid];
        }

        return static::$latestQueueCache[$sourceUuid] = app(CustomInvoiceQueueClient::class)
            ->findLatestBySourceDocumentUuid($sourceUuid);
    }

    private static function latestQueueStatusLabelForRecord(BuyerInvoice $record): string
    {
        $latest = static::findLatestQueueForRecord($record);

        if ($latest === null) {
            return '未登録（この請求書に対する修正キューはまだありません）';
        }

        $status = (string) ($latest['status'] ?? '-');
        $queueUuid = (string) ($latest['queue_uuid'] ?? '-');

        return "status={$status} / queue_uuid={$queueUuid}";
    }

    private static function isPublishedToInvoice(BuyerInvoice $record): bool
    {
        $uuid = trim((string) ($record->uuid ?? ''));

        if ($uuid === '') {
            return false;
        }

        if (array_key_exists($uuid, static::$publishedStateCache)) {
            return static::$publishedStateCache[$uuid];
        }

        try {
            return static::$publishedStateCache[$uuid] = app(DocumentPublishClient::class)->isPublished($uuid);
        } catch (Throwable) {
            return static::$publishedStateCache[$uuid] = false;
        }
    }

    private static function rememberPublishedState(string $uuid, bool $published): void
    {
        $uuid = trim($uuid);

        if ($uuid !== '') {
            static::$publishedStateCache[$uuid] = $published;
        }
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
