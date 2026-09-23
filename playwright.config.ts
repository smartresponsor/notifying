import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  reporter: 'list',
  use: {
    baseURL: process.env.NOTIFYING_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
  },
});
