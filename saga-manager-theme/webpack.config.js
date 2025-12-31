/**
 * Webpack Configuration for Saga Manager Theme
 *
 * Builds JSX files for Gutenberg blocks and panels
 *
 * @package SagaManager
 * @version 1.4.0
 */

const path = require('path');

module.exports = {
    entry: {
        'consistency-gutenberg-panel': './assets/js/consistency-gutenberg-panel.jsx',
        'consistency-badge-block': './assets/js/blocks/consistency-badge.jsx',
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/build'),
        filename: '[name].js',
    },
    module: {
        rules: [
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            '@babel/preset-env',
                            '@babel/preset-react'
                        ]
                    }
                }
            },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader']
            }
        ]
    },
    resolve: {
        extensions: ['.js', '.jsx']
    },
    externals: {
        'jquery': 'jQuery',
        'react': 'React',
        'react-dom': 'ReactDOM',
        '@wordpress/blocks': 'wp.blocks',
        '@wordpress/element': 'wp.element',
        '@wordpress/components': 'wp.components',
        '@wordpress/block-editor': 'wp.blockEditor',
        '@wordpress/data': 'wp.data',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/plugins': 'wp.plugins',
        '@wordpress/edit-post': 'wp.editPost'
    },
    optimization: {
        minimize: true
    }
};
