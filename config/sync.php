<?php

return [
    'partner_portal' => [
        'dry_run_enabled' => env('SYNC_PARTNER_PORTAL_DRY_RUN_ENABLED', true),
        'scope' => 'partner_portal',
        'gates' => [
            'min_runs' => (int) env('SYNC_PARTNER_PORTAL_GATE_MIN_RUNS', 5),
            'count_diff_ratio_max' => (float) env('SYNC_PARTNER_PORTAL_GATE_COUNT_DIFF_RATIO_MAX', 0.001),
            'error_rate_max' => (float) env('SYNC_PARTNER_PORTAL_GATE_ERROR_RATE_MAX', 0.005),
            'p95_duration_seconds_max' => (int) env('SYNC_PARTNER_PORTAL_GATE_P95_DURATION_SECONDS_MAX', 300),
        ],
    ],
];
