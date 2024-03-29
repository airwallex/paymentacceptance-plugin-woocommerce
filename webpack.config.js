const path                              = require('path');
const defaultConfig                     = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
	...defaultConfig,
	devtool:
		process.env.NODE_ENV === 'production'
			? 'hidden-source-map'
			: defaultConfig.devtool,
	optimization: {
		...defaultConfig.optimization,
		minimize: true,
		minimizer: [
			new TerserPlugin({
				terserOptions: {
					sourceMap: true,
				},
			}),
		],
		usedExports: true,
		splitChunks: undefined,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin({
			injectPolyfill: true,
		}),
	],
	module: {
		...defaultConfig.module,
		rules: [
		  ...defaultConfig.module.rules,
		  {
			test: /\.tsx?$/,
			use: [
			  {
				loader: 'ts-loader',
				options: {
				  configFile: 'tsconfig.json',
				  transpileOnly: true,
				}
			  }
			]        
		  }
		]
	  },
	resolve: {
		extensions: ['.json', '.js', '.jsx', '.ts', '.tsx'],
		modules: [path.join(__dirname, 'client'), path.join(__dirname, 'assets/js'), 'node_modules'],
		alias: {
		},
	},
	entry: {
		'airwallex-wc-blocks': './client/blocks/index.js',
		'airwallex-wc-ec-blocks': './client/blocks/expressCheckout/index.js',
		'airwallex-express-checkout': './assets/js/expressCheckout/airwallex-express-checkout.js',
		'airwallex-lpm': './assets/js/airwallex-lpm.js',
	},
	output: {
		path: path.resolve(__dirname, './build/'),
		filename: '[name].min.js',
	},
};
