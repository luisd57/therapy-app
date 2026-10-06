// @ts-check
import { defineConfig } from 'eslint/config';
import tseslint from 'typescript-eslint';
import playwright from 'eslint-plugin-playwright';
import vitest from '@vitest/eslint-plugin';

/** @typedef {import('eslint').Linter.RulesRecord} Rules */

// Both plugins ship some recommended rules as "warn", which never fails the pipeline.
/** @type {(rules: Partial<Rules> | undefined) => Rules} */
const asErrors = (rules) =>
  Object.fromEntries(
    Object.entries(rules ?? {}).map(([rule, severity]) => [
      rule,
      // "off" is the Playwright preset freeing `({}, use)` in fixtures from no-empty-pattern.
      severity === 'off' ? /** @type {const} */ ('off') : /** @type {const} */ ('error'),
    ]),
  );

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

  // Playwright specs and fixtures
  {
    files: ['e2e/**/*.ts'],
    extends: [playwright.configs['flat/recommended']],
    rules: {
      ...asErrors(playwright.configs['flat/recommended'].rules),
      // test.fixme stops a test running just as test.skip does, and is allowed by default.
      'playwright/no-skipped-test': ['error', { disallowFixme: true }],
    },
  },

  // Unit tests get the Vitest rules only, not the TypeScript standard above.
  {
    files: ['src/**/*.test.ts'],
    languageOptions: { parser: tseslint.parser },
    extends: [vitest.configs.recommended],
    rules: {
      ...asErrors(vitest.configs.recommended.rules),
      // Not in the recommended set. A test that branches proves different things on different runs.
      'vitest/no-conditional-in-test': 'error',
    },
  },
);
