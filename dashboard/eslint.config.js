// @ts-check
const tseslint = require("typescript-eslint");
const angular = require("angular-eslint");
const prettier = require("eslint-config-prettier");
const playwright = require("eslint-plugin-playwright");
const boundaries = require("eslint-plugin-boundaries");

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

  // Dependency Flow from .claude/rules/dashboard-angular.md, scoped to src/ since e2e/ has no domains.
  // One policy for cross-domain, then one per layer for the diagram's arrows and the two bullets.
  {
    files: ["src/**/*.ts"],
    plugins: { boundaries },
    settings: {
      "import/resolver": { node: { extensions: [".ts"] } },
      "boundaries/legacy-templates": false,
      // A cross-domain loadChildren is a dynamic import, which the plugin skips by default.
      "boundaries/dependency-nodes": ["import", "dynamic-import", "export"],
      // The composition root wires every domain together, so no rule applies to it.
      "boundaries/ignore": ["src/main.ts", "src/app/*.ts"],
      // src/environments/ is no element, so anything may import it: data-access reads the API URL there.
      "boundaries/elements": [
        ...["feature", "ui", "data-access", "utils"].map((layer) => ({
          type: layer,
          pattern: `src/app/*/${layer}`,
          capture: ["domain"],
        })),
        // A file outside a layer folder, so only feature/ may import it. Kept with no members as a guard.
        { type: "domain-root", pattern: "src/app/*", capture: ["domain"] },
      ],
    },
    rules: {
      "boundaries/dependencies": [
        "error",
        {
          default: "allow",
          policies: [
            {
              disallow: {
                to: { element: { captured: { domain: "!{{ from.element.captured.domain }}" } } },
              },
              message:
                "Cross-domain imports are forbidden. Shared needs go in shared/ (.claude/rules/dashboard-angular.md, Dependency Flow).",
            },
            // shared/ exists so domains have somewhere legal to meet. Must follow the
            // cross-domain policy, since the last matching policy wins.
            { allow: { to: { element: { captured: { domain: "shared" } } } } },
            {
              from: { element: { type: "ui" } },
              disallow: { to: { element: { types: ["feature", "data-access", "domain-root"] } } },
              message:
                "ui/ may import only ui/ and utils/ (.claude/rules/dashboard-angular.md, Dependency Flow).",
            },
            {
              from: { element: { type: "data-access" } },
              disallow: { to: { element: { types: ["feature", "ui", "domain-root"] } } },
              message:
                "data-access/ may import only data-access/ and utils/ (.claude/rules/dashboard-angular.md, Dependency Flow).",
            },
            {
              from: { element: { type: "utils" } },
              disallow: { to: { element: { types: ["feature", "ui", "data-access", "domain-root"] } } },
              message:
                "utils/ is the end of the flow and may import only utils/ (.claude/rules/dashboard-angular.md, Dependency Flow).",
            },
          ],
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
