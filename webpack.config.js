const path                              = require('path');
const defaultConfig                     = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

module.exports = {
	...defaultConfig,
	devtool:
		process.env.NODE_ENV === 'production'
			? 'hidden-source-map'
			: defaultConfig.devtool,
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			...defaultConfig.optimization.minimizer.map((plugin) => {
				if (plugin.constructor.name === 'TerserPlugin') {
					// wp-scripts does not allow to override the Terser minimizer sourceMap option, without this
					// `devtool: 'hidden-source-map'` is not generated for js files.
					plugin.options.sourceMap = true;
				}
				return plugin;
			}),
		],
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
resolve: {
	extensions: ['.json', '.js', '.jsx'],
	modules: [path.join(__dirname, 'client'), 'node_modules'],
	alias: {
		wcstripe: path.resolve(__dirname, 'client'),
	},
	},
	entry: {
		index: './client/blocks/index.js',
	},
};
