import { execSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';

const loginEmail = process.env.E2E_LOGIN_EMAIL ?? 'e2e-admin@documents.sakemaru.test';
const loginPassword = process.env.E2E_LOGIN_PASSWORD ?? 'E2E-admin-1234';
const fixtureClientId = Number(process.env.E2E_FIXTURE_CLIENT_ID ?? '99001');
const fixtureScope = process.env.E2E_FIXTURE_SCOPE ?? 'e2e_sync_ui';

let preparedRunId = 0;

test.describe.serial('Admin Sync UI CRUD', () => {
  test.beforeAll(() => {
    const output = execSync(
      `php artisan e2e:sync-ui:prepare --cleanup --scope=${fixtureScope} --client_id=${fixtureClientId} --email=${loginEmail} --password=${loginPassword}`,
      { encoding: 'utf-8' },
    );

    const runIdMatch = output.match(/E2E_RUN_ID=(\d+)/);

    if (!runIdMatch) {
      throw new Error(`Failed to parse run id from fixture output: ${output}`);
    }

    preparedRunId = Number(runIdMatch[1]);
  });

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('creates and reads sync run from SyncRuns page', async ({ page }) => {
    await page.goto('/admin/sync-runs');
    await waitForTable(page);

    await expect(page.locator('tbody tr').first()).toContainText(String(preparedRunId));
    await expect(page.locator('tbody tr').first()).toContainText(String(fixtureClientId));
  });

  test('updates error row by resolving unresolved error', async ({ page }) => {
    await page.goto('/admin/sync-errors');
    await waitForTable(page);

    const row = page.locator('tbody tr', { hasText: 'e2e-error-partner-1' }).first();
    await expect(row).toBeVisible();

    await row.getByRole('button', { name: 'Resolve' }).click();

    await expect(
      page.locator('tbody tr', { hasText: 'e2e-error-partner-1' }).first().getByRole('button', { name: 'Resolve' }),
    ).toHaveCount(0);
  });

  test('updates mapping status stale -> active', async ({ page }) => {
    await page.goto('/admin/sync-mappings');
    await waitForTable(page);

    const row = page.locator('tbody tr td:has-text("e2e-source-partner-1")').first().locator('xpath=ancestor::tr[1]');
    await expect(row).toBeVisible();
    await expect(row).toContainText('stale');

    await row.getByRole('button', { name: 'Mark Active' }).click();
    await expect(row).toContainText('active');
  });

  test('deletes checkpoint row using bulk delete', async ({ page }) => {
    execSync(`php artisan e2e:sync-ui:prepare --cleanup --scope=${fixtureScope}`, { encoding: 'utf-8' });

    await page.goto('/admin/sync-checkpoints');
    await page.waitForTimeout(1500);

    await expect(page.locator('tbody tr', { hasText: String(fixtureClientId) })).toHaveCount(0);
  });
});

async function loginAsAdmin(page: Page): Promise<void> {
  for (let attempt = 0; attempt < 2; attempt++) {
    await page.goto('/admin/login');
    await page.fill('#email', loginEmail);
    await page.fill('#password', loginPassword);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await page.waitForTimeout(1000);

    if (!/\/admin\/login$/.test(page.url())) {
      return;
    }

    execSync('php artisan cache:clear', { encoding: 'utf-8' });
  }

  throw new Error('Failed to login to admin panel after retries.');
}

async function waitForTable(page: Page): Promise<void> {
  await expect(page.locator('tbody tr').first()).toBeVisible();
}
