import { expect, test, type Page } from '@playwright/test';

const loginEmail = process.env.E2E_LOGIN_EMAIL ?? 'e2e-admin@documents.sakemaru.test';
const loginPassword = process.env.E2E_LOGIN_PASSWORD ?? 'E2E-admin-1234';

const adminPages = [
  '/admin',
  '/admin/documents',
  '/admin/users',
  '/admin/buyer-invoices',
  '/admin/sync-runs',
  '/admin/sync-run-items',
  '/admin/sync-errors',
  '/admin/sync-mappings',
  '/admin/sync-checkpoints',
];

test('admin pages smoke check in headless browser', async ({ page }) => {
  const runtimeErrors: string[] = [];

  page.on('pageerror', (error) => {
    runtimeErrors.push(`pageerror: ${error.message}`);
  });

  page.on('console', (message) => {
    if (message.type() === 'error') {
      runtimeErrors.push(`console[error]: ${message.text()}`);
    }
  });

  await loginAsAdmin(page);

  for (const path of adminPages) {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
    expect(response, `missing response for ${path}`).not.toBeNull();
    expect(response!.status(), `unexpected status for ${path}`).toBeLessThan(400);

    await expect(page.locator('body')).toBeVisible();
    await page.waitForTimeout(600);
  }

  await page.goto('/partner/login', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toBeVisible();

  expect(runtimeErrors).toEqual([]);
});

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.fill('#email', loginEmail);
  await page.fill('#password', loginPassword);
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/admin/login'));
}
