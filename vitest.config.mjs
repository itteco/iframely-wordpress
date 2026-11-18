import { transformWithOxc } from 'vite';
import { defineConfig } from 'vitest/config';

/*
 * The plugin's source keeps JSX in .js files, which wp-scripts builds with Babel.
 * Vite only reads JSX from .jsx files, so the source and its tests are transformed here.
 */
const jsxInJs = {
  name: 'iframely:jsx-in-js',
  enforce: 'pre',
  transform(code, id) {
    if (!/\/(src|tests\/js)\/.+\.js$/.test(id)) {
      return null;
    }
    return transformWithOxc(code, id, { lang: 'jsx', jsx: { runtime: 'automatic' } });
  },
};

export default defineConfig({
  plugins: [jsxInJs],
  test: {
    environment: 'jsdom',
    globals: false,
    include: ['tests/js/**/*.test.js'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.js'],
      reportsDirectory: 'artifacts/coverage-js',
    },
  },
});
