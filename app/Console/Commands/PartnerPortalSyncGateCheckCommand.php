<?php

namespace App\Console\Commands;

use App\Services\PartnerPortalSyncGateService;
use Illuminate\Console\Command;

class PartnerPortalSyncGateCheckCommand extends Command
{
    protected $signature = 'sync:partner-portal:gate-check {--client_id=}';

    protected $description = 'Evaluate dry-run metrics against Phase B gate thresholds.';

    public function handle(PartnerPortalSyncGateService $service): int
    {
        $clientOption = $this->option('client_id');
        $clientId = $clientOption !== null ? (int) $clientOption : null;

        $result = $service->evaluate($clientId);

        $this->info('Partner portal gate evaluation finished.');
        $this->line('Result: '.($result['passed'] ? 'PASS' : 'FAIL'));
        $this->line('Reason: '.$result['reason']);
        $this->line('Runs: '.$result['actual_runs'].' / '.$result['required_runs']);

        if (($result['checks'] ?? []) !== []) {
            $rows = [];

            foreach ($result['checks'] as $name => $check) {
                $rows[] = [
                    $name,
                    (string) $check['value'],
                    (string) $check['threshold'],
                    $check['passed'] ? 'PASS' : 'FAIL',
                ];
            }

            $this->table(['check', 'value', 'threshold', 'result'], $rows);
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
