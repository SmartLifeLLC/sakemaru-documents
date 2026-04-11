<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerPortalSyncGateService
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(?int $clientId = null): array
    {
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');
        $minRuns = (int) config('sync.partner_portal.gates.min_runs', 5);
        $countDiffRatioMax = (float) config('sync.partner_portal.gates.count_diff_ratio_max', 0.001);
        $errorRateMax = (float) config('sync.partner_portal.gates.error_rate_max', 0.005);
        $p95DurationSecondsMax = (int) config('sync.partner_portal.gates.p95_duration_seconds_max', 300);

        $runs = DB::connection('mysql')
            ->table('sync_runs')
            ->where('sync_scope', $scope)
            ->where('mode', 'dry_run')
            ->where('status', 'success')
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->orderByDesc('id')
            ->limit($minRuns)
            ->get()
            ->reverse()
            ->values();

        if ($runs->count() < $minRuns) {
            return [
                'passed' => false,
                'reason' => 'insufficient_runs',
                'required_runs' => $minRuns,
                'actual_runs' => $runs->count(),
                'client_id' => $clientId,
                'checks' => [],
            ];
        }

        $durations = $this->collectDurationsInSeconds($runs);
        $scanned = $runs->pluck('scanned_count')->map(fn ($v) => (int) $v);
        $errors = $runs->pluck('error_count')->map(fn ($v) => (int) $v);

        $minScanned = max(1, $scanned->min());
        $maxScanned = max(1, $scanned->max());
        $countDiffRatio = ($maxScanned - $minScanned) / $maxScanned;

        $totalScanned = max(1, (int) $scanned->sum());
        $totalErrors = (int) $errors->sum();
        $errorRate = $totalErrors / $totalScanned;

        $p95DurationSeconds = $this->calculateP95($durations);

        $checks = [
            'count_diff_ratio' => [
                'value' => $countDiffRatio,
                'threshold' => $countDiffRatioMax,
                'passed' => $countDiffRatio <= $countDiffRatioMax,
            ],
            'error_rate' => [
                'value' => $errorRate,
                'threshold' => $errorRateMax,
                'passed' => $errorRate <= $errorRateMax,
            ],
            'p95_duration_seconds' => [
                'value' => $p95DurationSeconds,
                'threshold' => $p95DurationSecondsMax,
                'passed' => $p95DurationSeconds <= $p95DurationSecondsMax,
            ],
            'idempotency_same_scanned_count' => [
                'value' => $maxScanned - $minScanned,
                'threshold' => 0,
                'passed' => ($maxScanned - $minScanned) === 0,
            ],
        ];

        $passed = collect($checks)->every(fn (array $check) => $check['passed'] === true);

        return [
            'passed' => $passed,
            'reason' => $passed ? 'all_checks_passed' : 'threshold_failed',
            'required_runs' => $minRuns,
            'actual_runs' => $runs->count(),
            'client_id' => $clientId,
            'checks' => $checks,
            'run_ids' => $runs->pluck('id')->all(),
        ];
    }

    private function collectDurationsInSeconds(Collection $runs): array
    {
        return $runs
            ->map(function ($run): int {
                if (empty($run->started_at) || empty($run->finished_at)) {
                    return 0;
                }

                $started = strtotime((string) $run->started_at);
                $finished = strtotime((string) $run->finished_at);

                if ($started === false || $finished === false || $finished < $started) {
                    return 0;
                }

                return $finished - $started;
            })
            ->all();
    }

    /**
     * @param array<int> $durations
     */
    private function calculateP95(array $durations): int
    {
        if ($durations === []) {
            return 0;
        }

        sort($durations);

        $index = (int) ceil(count($durations) * 0.95) - 1;
        $index = max(0, min($index, count($durations) - 1));

        return $durations[$index];
    }
}
