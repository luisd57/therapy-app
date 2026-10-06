// @ts-check
import { defineConfig } from 'eslint/config';
import tseslint from 'typescript-eslint';
import playwright from 'eslint-plugin-playwright';
import vitest from '@vitest/eslint-plugin';

/**
 * Both plugins ship some recommended rules as "warn", which never fails the pipeline.
 * @param {Partial<import('eslint').Linter.RulesRecord> | undefined} rules
 * @returns {import('eslint').Linter.RulesRecord}
 */
function asErrors(rules) {
  return Object.fromEntries(
    Object.entries(rules ?? {}).map(([rule, severity]) => [
      rule,
      severity === 'off' ? /** @type {const} */ ('off') : /** @type {const} */ ('error'),
    ]),
  );
}

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
    rules: asErrors(playwright.configs['flat/recommended'].rules),
  },

  // Unit tests get the Vitest rules only. The TypeScript standard above stays scoped to e2e,
  // as ticket 10 left it.
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
