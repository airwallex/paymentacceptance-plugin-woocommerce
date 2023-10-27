// @ts-check
const { defineConfig, devices } = require('@playwright/test');
require('dotenv').config();

export const STORAGE_STATE = __dirname + '/tests/e2e/storageState.json';

/**
 * E2E test config file
 *
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
	testDir: './tests/e2e',
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !!process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : undefined,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI ? 'dot' : 'html',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Base URL to use in actions like `await page.goto('/')`. */
		// baseURL: process.env.BASEURL,
		baseURL: process.env.BASE_URL,

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',
	},

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'setup',
			testMatch: '**/*.setup.js',
	},
		{
			name: 'woocommerce-payments-tab',
			testDir: './tests/e2e/WooCommercePaymentsTab',
			dependencies: ['setup'],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
	},
	],
});
