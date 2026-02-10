<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrepareSyncUiE2eFixturesCommand extends Command
{
    protected $signature = 'e2e:sync-ui:prepare
        {--email=e2e-admin@documents.sakemaru.test : Admin login email for E2E}
        {--password=E2E-admin-1234 : Admin login password for E2E}
        {--client_id=1 : Target client id}
        {--scope=e2e_sync_ui : Fixture sync scope}
        {--cleanup : Cleanup fixtures for scope before inserting}';

    protected $description = 'Prepare deterministic admin user and sync fixture rows for headless UI E2E tests.';

    public function handle(): int
    {
        $core = DB::connection('sakemaru');
        $meta = DB::connection('mysql');
        $now = now();

        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $clientId = (int) $this->option('client_id');
        $scope = (string) $this->option('scope');
        $cleanup = (bool) $this->option('cleanup');

        if ($cleanup) {
            $this->cleanupScope($scope);
        }

        $seedUserId = (int) ($core->table('users')->orderBy('id')->value('id') ?? 1);

        $existingUser = $core->table('users')
            ->where('email', $email)
            ->first(['id', 'code']);

        if ($existingUser === null) {
            $nextCode = (int) (($core->table('users')->max('code') ?? 0) + 1);
            $userId = (int) $core->table('users')->insertGetId([
                'code' => $nextCode,
                'client_id' => $clientId,
                'name' => 'E2E Admin',
                'kana_name' => null,
                'email' => $email,
                'default_branch_id' => null,
                'default_warehouse_id' => null,
                'permission_ship_rare_item' => 0,
                'invalidation_date' => null,
                'is_active' => 1,
                'font_size' => 'SMALL',
                'creator_id' => $seedUserId,
                'last_updater_id' => $seedUserId,
                'email_verified_at' => $now,
                'password' => Hash::make($password),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'as_api_user' => 0,
                'current_team_id' => null,
                'profile_photo_path' => null,
                'last_use_printer_driver_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'is_created_from_data_transfer' => 0,
            ]);
        } else {
            $userId = (int) $existingUser->id;
            $core->table('users')
                ->where('id', $userId)
                ->update([
                    'client_id' => $clientId,
                    'name' => 'E2E Admin',
                    'is_active' => 1,
                    'password' => Hash::make($password),
                    'last_updater_id' => $seedUserId,
                    'updated_at' => $now,
                ]);
        }

        $runId = (int) $meta->table('sync_runs')->insertGetId([
            'sync_scope' => $scope,
            'mode' => 'dry_run',
            'status' => 'success',
            'client_id' => $clientId,
            'checkpoint_from' => json_encode(['cursor_updated_at' => null, 'cursor_id' => 0, 'last_run_id' => null], JSON_UNESCAPED_UNICODE),
            'checkpoint_to' => json_encode(['cursor_updated_at' => '2026-02-10 00:00:00', 'cursor_id' => 999999, 'last_run_id' => 0], JSON_UNESCAPED_UNICODE),
            'scanned_count' => 10,
            'upsert_count' => 8,
            'skip_count' => 2,
            'error_count' => 0,
            'started_at' => $now->copy()->subMinutes(1),
            'finished_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $meta->table('sync_run_items')->insert([
            'run_id' => $runId,
            'entity_type' => 'partner',
            'source_client_id' => $clientId,
            'source_pk' => 'e2e-source-partner-1',
            'operation' => 'insert',
            'idempotency_key' => hash('sha256', 'e2e|partner|1'),
            'payload_hash' => hash('sha256', 'e2e-payload-1'),
            'result_status' => 'success',
            'attempt_count' => 1,
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $meta->table('sync_errors')->insert([
            'run_id' => $runId,
            'run_item_id' => null,
            'entity_type' => 'partner',
            'source_client_id' => $clientId,
            'source_pk' => 'e2e-error-partner-1',
            'stage' => 'apply',
            'error_code' => 'e2e_fixture_error',
            'error_message' => 'fixture unresolved error',
            'error_context' => json_encode(['fixture' => true], JSON_UNESCAPED_UNICODE),
            'is_retryable' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $meta->table('sync_checkpoints')->upsert(
            [[
                'sync_scope' => $scope,
                'entity_type' => 'partner',
                'client_id' => $clientId,
                'cursor_updated_at' => '2026-02-10 00:00:00',
                'cursor_id' => 123456,
                'last_run_id' => $runId,
                'lock_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['sync_scope', 'entity_type', 'client_id'],
            ['cursor_updated_at', 'cursor_id', 'last_run_id', 'updated_at']
        );

        $meta->table('sync_mappings')->upsert(
            [[
                'entity_type' => 'partner',
                'source_client_id' => $clientId,
                'source_id' => 'e2e-source-partner-1',
                'source_code' => 'E2E001',
                'target_system' => 'invoice',
                'target_id' => 'e2e-target-1',
                'mapping_status' => 'stale',
                'confidence' => 1.00,
                'first_synced_at' => $now,
                'last_synced_at' => $now,
                'stale_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['entity_type', 'source_client_id', 'source_id', 'target_system'],
            ['source_code', 'target_id', 'mapping_status', 'confidence', 'last_synced_at', 'stale_at', 'updated_at']
        );

        $this->info("Prepared fixtures for scope={$scope} client_id={$clientId}");
        $this->line("E2E_LOGIN_EMAIL={$email}");
        $this->line("E2E_LOGIN_PASSWORD={$password}");
        $this->line("E2E_USER_ID={$userId}");
        $this->line("E2E_RUN_ID={$runId}");

        return self::SUCCESS;
    }

    private function cleanupScope(string $scope): void
    {
        $meta = DB::connection('mysql');
        $runIds = $meta->table('sync_runs')->where('sync_scope', $scope)->pluck('id')->all();

        if ($runIds !== []) {
            $meta->table('sync_run_items')->whereIn('run_id', $runIds)->delete();
            $meta->table('sync_errors')->whereIn('run_id', $runIds)->delete();
            $meta->table('sync_runs')->whereIn('id', $runIds)->delete();
        }

        $meta->table('sync_checkpoints')->where('sync_scope', $scope)->delete();
        $meta->table('sync_mappings')->where('source_id', 'e2e-source-partner-1')->delete();
    }
}
