<?php

namespace App\Filament\Resources\SyncRunsResource\Pages;

use App\Filament\Resources\SyncRunsResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListSyncRuns extends ListRecords
{
    protected static string $resource = SyncRunsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dryRun')
                ->label('Dry Run')
                ->icon('heroicon-o-play')
                ->form([
                    TextInput::make('client_id')->label('Client ID')->numeric()->default(1)->required(),
                ])
                ->action(function (array $data): void {
                    $exitCode = Artisan::call('sync:partner-portal:dry-run', [
                        '--client_id' => (int) $data['client_id'],
                    ]);

                    $this->notifyResult($exitCode, Artisan::output(), 'Dry run executed');
                })
                ->requiresConfirmation(),
            Action::make('applyPartners')
                ->label('Apply Partners')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    TextInput::make('client_id')->label('Client ID')->numeric()->default(1)->required(),
                    TextInput::make('limit')->label('Limit')->numeric()->default(20000)->required(),
                    Toggle::make('from_start')->label('From Start')->default(false),
                ])
                ->action(function (array $data): void {
                    $exitCode = Artisan::call('sync:partner-portal:apply', [
                        '--client_id' => (int) $data['client_id'],
                        '--limit' => (int) $data['limit'],
                        '--from_start' => (bool) ($data['from_start'] ?? false),
                    ]);

                    $this->notifyResult($exitCode, Artisan::output(), 'Partner apply executed');
                })
                ->requiresConfirmation(),
            Action::make('applyInvoices')
                ->label('Apply Invoices')
                ->icon('heroicon-o-document-text')
                ->form([
                    TextInput::make('client_id')->label('Client ID')->numeric()->default(1)->required(),
                    TextInput::make('limit')->label('Limit')->numeric()->default(5000)->required(),
                    Toggle::make('from_start')->label('From Start')->default(false),
                ])
                ->action(function (array $data): void {
                    $exitCode = Artisan::call('sync:partner-portal:apply-invoices', [
                        '--client_id' => (int) $data['client_id'],
                        '--limit' => (int) $data['limit'],
                        '--from_start' => (bool) ($data['from_start'] ?? false),
                    ]);

                    $this->notifyResult($exitCode, Artisan::output(), 'Invoice apply executed');
                })
                ->requiresConfirmation(),
            Action::make('gateCheck')
                ->label('Gate Check')
                ->icon('heroicon-o-shield-check')
                ->form([
                    TextInput::make('client_id')->label('Client ID')->numeric()->default(1)->required(),
                ])
                ->action(function (array $data): void {
                    $exitCode = Artisan::call('sync:partner-portal:gate-check', [
                        '--client_id' => (int) $data['client_id'],
                    ]);

                    $this->notifyResult($exitCode, Artisan::output(), 'Gate check executed');
                }),
        ];
    }

    private function notifyResult(int $exitCode, string $output, string $title): void
    {
        $message = trim($output);
        if ($message === '') {
            $message = 'No output.';
        }

        Notification::make()
            ->title($title)
            ->body(mb_substr($message, 0, 1000))
            ->{$exitCode === 0 ? 'success' : 'danger'}()
            ->send();
    }
}
