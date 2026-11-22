import js from '@eslint/js';
import globals from 'globals';
export default [
  js.configs.recommended,
  { files: ['**/app.js'], languageOptions: { ecmaVersion: 2020, sourceType: 'script', globals: { ...globals.browser } },
    rules: { 'no-unused-vars': ['error', { args: 'all', caughtErrors: 'all' }], 'no-shadow': 'error', 'no-use-before-define': ['error', { functions: false }], eqeqeq: ['error', 'always', { null: 'ignore' }], 'no-var': 'off', 'prefer-const': 'off' } },
  { files: ['**/e2e.js'], languageOptions: { ecmaVersion: 2022, sourceType: 'commonjs', globals: { ...globals.node, ...globals.browser } },
    rules: { 'no-unused-vars': ['error', { args: 'all', caughtErrors: 'all' }], 'no-shadow': 'error', eqeqeq: ['error', 'always', { null: 'ignore' }] } },
];
