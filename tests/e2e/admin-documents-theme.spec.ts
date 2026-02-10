import { expect, test, type Page } from '@playwright/test';

const loginEmail = process.env.E2E_LOGIN_EMAIL ?? 'e2e-admin@documents.sakemaru.test';
const loginPassword = process.env.E2E_LOGIN_PASSWORD ?? 'E2E-admin-1234';

test('admin documents page applies mega menu tailwind styles', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/admin/documents');
  await page.waitForLoadState('networkidle');

  const megaMenu = page.locator('div.w-full.bg-slate-800.text-slate-200.h-10').first();
  await expect(megaMenu).toBeVisible();

  const style = await megaMenu.evaluate((element) => {
    const computed = window.getComputedStyle(element);

    return {
      backgroundColor: computed.backgroundColor,
      color: computed.color,
      display: computed.display,
      height: computed.height,
    };
  });

  expect(style.display).toBe('flex');
  expect(style.height).toBe('40px');
  expect(style.backgroundColor).not.toBe('rgba(0, 0, 0, 0)');
});

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.fill('#email', loginEmail);
  await page.fill('#password', loginPassword);
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/admin/login'));
}
