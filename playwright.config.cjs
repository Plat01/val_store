const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './site/tests', timeout: 90000, workers: 1,
  use: { baseURL: process.env.WP_URL || 'http://localhost:8080', browserName: 'chromium', trace: 'retain-on-failure' },
});
