import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // Unit tests only. e2e/ belongs to Playwright, and Vitest picking those up
    // fails with a confusing "did not expect test.describe() to be called here".
    include: ['src/**/*.test.ts'],
  },
});
