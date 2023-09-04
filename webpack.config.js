const path = require("path");
const TerserPlugin = require("terser-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const OptimizeCSSAssetsPlugin = require("css-minimizer-webpack-plugin");
const FixStyleOnlyEntriesPlugin = require("webpack-fix-style-only-entries");

module.exports = {
  entry: {
    "./assets/js/rtafar.app.admin": "./src/js/app.js",
    "./assets/js/rtafar.admin.global": "./src/js/app.global.js",
    "./assets/css/rtafar-admin-style": "./src/scss/app.scss",
    "./assets/css/rtafar-admin-global-style": "./src/scss/global.scss",
    "./assets/js/rtafar.app": "./src/js/ajaxContentReplacer.js",
    "./assets/js/rtafar.admin.replace.in.db": "./src/js/pageReplaceInDb.js",
  },
  output: {
    filename: "[name].min.js",
    path: path.resolve(__dirname),
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader",
          options: {
            presets: ["@babel/preset-env"],
          },
        },
      },
      {
        test: /\.(sass|scss)$/,
        use: [
          MiniCssExtractPlugin.loader,
          "css-loader",
          "postcss-loader",
          "sass-loader",
        ],
      },
    ],
  },
  plugins: [
    new FixStyleOnlyEntriesPlugin(),
    new MiniCssExtractPlugin({
      filename: "[name].min.css",
    }),
  ],
  optimization: {
    minimizer: [
      new TerserPlugin({
        minify: TerserPlugin.swcMinify,
        terserOptions: {
          format: {
            comments: false,
          },
        },
        extractComments: false,
      }),
      new OptimizeCSSAssetsPlugin({}),
    ],
  },
};
