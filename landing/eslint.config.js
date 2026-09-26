// @ts-check
import { defineConfig } from 'eslint/config';
import tseslint from 'typescript-eslint';

// Same TypeScript standard as dashboard/eslint.config.js, minus the Angular parts.
// Scoped to e2e by ticket 10. src/ .ts files need no plugin and can join, .astro and .svelte cannot.
export default defineConfig(
  {
    files: ['e2e/**/*.ts', 'playwright.config.ts'],
    languageOptions: {
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    extends: [...tseslint.configs.strictTypeChecked],
    rules: {
      '@typescript-eslint/typedef': [
        'error',
        {
          arrayDestructuring: true,
          arrowParameter: true,
          memberVariableDeclaration: true,
          objectDestructuring: true,
          parameter: true,
          propertyDeclaration: true,
          variableDeclaration: true,
          variableDeclarationIgnoreFunction: false,
        },
      ],

      '@typescript-eslint/explicit-function-return-type': [
        'error',
        {
          allowExpressions: false,
          allowTypedFunctionExpressions: false,
          allowHigherOrderFunctions: false,
          allowDirectConstAssertionInArrowFunctions: false,
          allowConciseArrowFunctionExpressionsStartingWithVoid: false,
        },
      ],

      '@typescript-eslint/explicit-module-boundary-types': [
        'error',
        {
          allowArgumentsExplicitlyTypedAsAny: false,
          allowDirectConstAssertionInArrowFunctions: false,
          allowHigherOrderFunctions: false,
          allowTypedFunctionExpressions: false,
        },
      ],

      // Must be OFF - otherwise it strips "redundant" annotations
      '@typescript-eslint/no-inferrable-types': 'off',
    },
  },
);
