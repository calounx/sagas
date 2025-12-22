/**
 * Custom Webpack configuration for Saga Manager Display blocks
 *
 * Configures wp-scripts to build blocks from the blocks/ directory
 * instead of the default src/ directory.
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const CopyPlugin = require('copy-webpack-plugin');

module.exports = {
    ...defaultConfig,
    entry: {
        'blocks/entity-display/index': path.resolve(__dirname, 'blocks/entity-display/index.js'),
        'blocks/search/index': path.resolve(__dirname, 'blocks/search/index.js'),
        'blocks/timeline/index': path.resolve(__dirname, 'blocks/timeline/index.js'),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, 'build'),
    },
    plugins: [
        ...defaultConfig.plugins,
        new CopyPlugin({
            patterns: [
                // Copy block.json files
                {
                    from: 'blocks/*/block.json',
                    to: '[path][name][ext]',
                },
                // Copy CSS files
                {
                    from: 'blocks/*/*.css',
                    to: ({ absoluteFilename }) => {
                        // Rename editor.css to index.css and style.css to style-index.css
                        const relativePath = path.relative(__dirname, absoluteFilename);
                        const dir = path.dirname(relativePath);
                        const basename = path.basename(relativePath);

                        if (basename === 'editor.css') {
                            return path.join(dir, 'index.css');
                        } else if (basename === 'style.css') {
                            return path.join(dir, 'style-index.css');
                        }
                        return relativePath;
                    },
                },
            ],
        }),
    ],
};
