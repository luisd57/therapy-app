// @ts-check
const tseslint = require("typescript-eslint");
const angular = require("angular-eslint");
const prettier = require("eslint-config-prettier");
const playwright = require("eslint-plugin-playwright");

// The plugin ships most of its recommended rules as "warn", which never fails the pipeline.
const playwrightRulesAsErrors = Object.fromEntries(
  Object.entries(playwright.configs["flat/recommended"].rules).map(
    // "off" is the preset freeing `({}, use)` in fixtures from no-empty-pattern.
    ([rule, severity]) => [rule, severity === "off" ? "off" : "error"],
  ),
);

module.exports = tseslint.config(
  {
    ignores: ["dist/", ".angular/", "node_modules/", "coverage/"],
  },

  // TypeScript files
  {
    files: ["**/*.ts"],
    languageOptions: {
      parser: tseslint.parser,
      parserOptions: {
        projectService: true,
        tsconfigRootDir: __dirname,
      },
    },
    extends: [
      ...tseslint.configs.strictTypeChecked,
      ...angular.configs.tsRecommended,
    ],
    processor: angular.processInlineTemplates,
    rules: {
      // ── Explicit types everywhere ──────────────────────────────

      "@typescript-eslint/typedef": [
        "error",
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

      "@typescript-eslint/explicit-function-return-type": [
        "error",
        {
          allowExpressions: false,
          allowTypedFunctionExpressions: false,
          allowHigherOrderFunctions: false,
          allowDirectConstAssertionInArrowFunctions: false,
          allowConciseArrowFunctionExpressionsStartingWithVoid: false,
        },
      ],

      "@typescript-eslint/explicit-module-boundary-types": [
        "error",
        {
          allowArgumentsExplicitlyTypedAsAny: false,
          allowDirectConstAssertionInArrowFunctions: false,
          allowHigherOrderFunctions: false,
          allowTypedFunctionExpressions: false,
        },
      ],

      // Must be OFF - otherwise it strips "redundant" annotations
      "@typescript-eslint/no-inferrable-types": "off",

      // Angular components are decorated empty classes by design
      "@typescript-eslint/no-extraneous-class": "off",

      // Deprecated API detection handled by Angular update tooling, not lint
      "@typescript-eslint/no-deprecated": "off",

      // Validators.required etc. are static methods passed as references
      "@typescript-eslint/unbound-method": ["error", { ignoreStatic: true }],

      // ── Angular ────────────────────────────────────────────────

      "@angular-eslint/directive-selector": [
        "error",
        { type: "attribute", prefix: "app", style: "camelCase" },
      ],
      "@angular-eslint/component-selector": [
        "error",
        { type: "element", prefix: "app", style: "kebab-case" },
      ],
    },
  },

  // Route files - relax arrow typing for lazy-load patterns
  {
    files: ["**/app.routes.ts", "**/*-shell.routes.ts"],
    rules: {
      "@typescript-eslint/explicit-function-return-type": "off",
      "@typescript-eslint/typedef": [
        "error",
        {
          arrayDestructuring: true,
          arrowParameter: false,
          memberVariableDeclaration: true,
          objectDestructuring: true,
          parameter: true,
          propertyDeclaration: true,
          variableDeclaration: true,
          variableDeclarationIgnoreFunction: false,
        },
      ],
    },
  },

  // HTML templates
  {
    files: ["**/*.html"],
    extends: [
      ...angular.configs.templateRecommended,
      ...angular.configs.templateAccessibility,
    ],
  },

  // Playwright specs and fixtures
  {
    files: ["e2e/**/*.ts"],
    extends: [playwright.configs["flat/recommended"]],
    rules: {
      ...playwrightRulesAsErrors,
      // test.fixme stops a test running just as test.skip does, and is allowed by default.
      "playwright/no-skipped-test": ["error", { disallowFixme: true }],
    },
  },

  // Prettier must be last
  prettier,
);
