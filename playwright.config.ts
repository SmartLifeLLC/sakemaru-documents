import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://documents.sakemaru.test',
    ignoreHTTPSErrors: true,
    headless: true,
    viewport: { width: 1600, height: 1000 },
  },
});
