// @ts-check
import 'dotenv/config';
import { defineConfig, devices } from '@playwright/test';

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
	retries: 2,
	/* Number of workers to use */
	workers: 4,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI ? 'dot' : 'html',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL: process.env.BASE_URL,

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',
	},
	expect: {
		timeout: 15000,
	},
	timeout: 2 * 60 * 1000,
	/* Configure projects for major browsers */
	projects: [
		{
			name: 'plugin_settings',
			testDir: './tests/e2e/pluginSettings',
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
		},
		{
			name: 'use-block-checkout-legacy-template',
			testMatch: 'block_checkout_legacy_template.setup.js',
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['plugin_settings'],
		},
		// {
		// 	name: 'google-pay',
		// 	testDir: './tests/e2e/googlePay',
		// 	use: {
		// 		...devices['Desktop Chrome'],
		// 	},
		// 	dependencies: ['plugin-settings'],
		// },
		{
			name: 'test-block-checkout-legacy-template',
			testMatch: [
				'block_card_redirect_legacy.spec.js',
				'block_dropin_legacy.spec.js',
				'block_wechat_legacy.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['use-block-checkout-legacy-template'],
		},
		{
			name: 'use-block-checkout-wp-page-template',
			testMatch: 'block_checkout_wp_page_template.setup.js',
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-block-checkout-legacy-template'],
		},
		{
			name: 'test-block-checkout-wp-page-template',
			testMatch: [
				'block_card_redirect_wp_page.spec.js',
				'block_dropin_wp_page.spec.js',
				'block_wechat_wp_page.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['use-block-checkout-wp-page-template'],
		},
		{
			name: 'test-block-checkout-embedded-card',
			testMatch: [
				'block_card_embedded.spec.js',
				'block_klarna.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-block-checkout-wp-page-template'],
		},
		{
			name: 'use-shortcode-checkout-legacy-template',
			testMatch: 'shortcode_checkout_legacy_template.setup.js',
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-block-checkout-embedded-card'],
		},
		{
			name: 'test-shortcode-checkout-legacy-template',
			testMatch: [
				'shortcode_card_redirect_legacy.spec.js',
				'shortcode_dropin_legacy.spec.js',
				'shortcode_wechat_legacy.spec.js',
				'orderPay_card_redirect_legacy.spec.js',
				'orderPay_dropin_legacy.spec.js',
				'orderPay_wechat_legacy.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['use-shortcode-checkout-legacy-template'],
		},
		{
			name: 'use-shortcode-checkout-wp-page-template',
			testMatch: 'shortcode_checkout_wp_page_template.setup.js',
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-shortcode-checkout-legacy-template'],
		},
		{
			name: 'test-shortcode-checkout-wp-page-template',
			testMatch: [
				'shortcode_card_redirect_wp_page.spec.js',
				'shortcode_dropin_wp_page.spec.js',
				'shortcode_wechat_wp_page.spec.js',
				'orderPay_card_redirect_wp_page.spec.js',
				'orderPay_dropin_wp_page.spec.js',
				'orderPay_wechat_wp_page.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['use-shortcode-checkout-wp-page-template'],
		},
		{
			name: 'test-shortcode-checkout-embedded-card',
			testMatch: [
				'shortcode_card_embedded.spec.js',
				'shortcode_klarna.spec.js',
				'orderPay_card_embedded.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-shortcode-checkout-wp-page-template'],
		},
		{
			name: 'Revert plugin settings changes',
			testMatch: [
				'airwallex_plugin_settings.spec.js',
			],
			use: {
				...devices['Desktop Chrome'],
				storageState: STORAGE_STATE,
			},
			dependencies: ['test-shortcode-checkout-embedded-card'],
		},
	],
});
