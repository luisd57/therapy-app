# Landing surviving mutants

**Evidence, not a backlog.** Same rule as `api-survivors.md`. Discovery only, there is no CI gate
on the landing side.

| | |
|---|---|
| Date | 2026-10-08 |
| Tool | Stryker 10.0.0 with the Vitest runner, Vitest 2.1 |
| Command | `npm run mutation` in `landing/` |
| Mutated | `src/utils/dates.ts`, `src/utils/modality.ts` |
| Wall time | 13s |
| Mutants | 131 |
| Killed | 88 |
| Survived | 15 |
| No coverage | 28 |
| Timed out, errors | 0, 0 |
| Mutation score, covered only | 67.18%, 85.44% |

Unlike Infection, Stryker also mutates code no test reaches and reports it as "no coverage".
Those are listed separately below.

No `test-suite-hardening` ticket covers these two files, so nothing here is annotated.
`landing/e2e/` is not measured: Vitest does not include it (ticket 16, comment of 2026-09-23).

| File | Killed | Survived | No coverage |
|---|---|---|---|
| `src/utils/dates.ts` | 83 | 15 | 28 |
| `src/utils/modality.ts` | 5 | 0 | 0 |

## Survived

| File | Line | Mutator | Original | Replacement |
|---|---|---|---|---|
| `src/utils/dates.ts` | 79 | ConditionalExpression | `found === null || new Date(instant) < new Date(found)` | `true` |
| `src/utils/dates.ts` | 79 | EqualityOperator | `new Date(instant) < new Date(found)` | `new Date(instant) <= new Date(found)` |
| `src/utils/dates.ts` | 161 | OptionalChaining | `timeZone.split('/').pop()?.replace` | `timeZone.split('/').pop().replace` |
| `src/utils/dates.ts` | 161 | StringLiteral | `' '` | `""` |
| `src/utils/dates.ts` | 167 | OptionalChaining | `parts.find((part) => part.type === 'timeZoneName')?.value` | `parts.find(part => part.type === 'timeZoneName').value` |
| `src/utils/dates.ts` | 198 | OptionalChaining | `parts.find((part) => part.type === type)?.value` | `parts.find(part => part.type === type).value` |
| `src/utils/dates.ts` | 201 | StringLiteral | `'year'` | `""` |
| `src/utils/dates.ts` | 202 | StringLiteral | `'month'` | `""` |
| `src/utils/dates.ts` | 202 | ArithmeticOperator | `lookup('month') - 1` | `lookup('month') + 1` |
| `src/utils/dates.ts` | 203 | StringLiteral | `'day'` | `""` |
| `src/utils/dates.ts` | 205 | StringLiteral | `'minute'` | `""` |
| `src/utils/dates.ts` | 206 | StringLiteral | `'second'` | `""` |
| `src/utils/dates.ts` | 209 | ArithmeticOperator | `asUtc - Math.floor(at.getTime() / 1000) * 1000` | `asUtc + Math.floor(at.getTime() / 1000) * 1000` |
| `src/utils/dates.ts` | 209 | ArithmeticOperator | `at.getTime() / 1000` | `at.getTime() * 1000` |
| `src/utils/dates.ts` | 209 | ArithmeticOperator | `Math.floor(at.getTime() / 1000) * 1000` | `Math.floor(at.getTime() / 1000) / 1000` |

## No coverage

| File | Line | Mutator | Original | Replacement |
|---|---|---|---|---|
| `src/utils/dates.ts` | 19 | BlockStatement | `{ ...` | `{}` |
| `src/utils/dates.ts` | 20 | ConditionalExpression | `Intl.DateTimeFormat().resolvedOptions().timeZone || PRACTICE_TIMEZONE_FALLBACK` | `true` |
| `src/utils/dates.ts` | 20 | ConditionalExpression | `Intl.DateTimeFormat().resolvedOptions().timeZone || PRACTICE_TIMEZONE_FALLBACK` | `false` |
| `src/utils/dates.ts` | 20 | LogicalOperator | `Intl.DateTimeFormat().resolvedOptions().timeZone || PRACTICE_TIMEZONE_FALLBACK` | `Intl.DateTimeFormat().resolvedOptions().timeZone && PRACTICE_TIMEZONE_FALLBACK` |
| `src/utils/dates.ts` | 27 | BlockStatement | `{ ...` | `{}` |
| `src/utils/dates.ts` | 29 | ConditionalExpression | `typeof navigator !== 'undefined'` | `true` |
| `src/utils/dates.ts` | 29 | ConditionalExpression | `typeof navigator !== 'undefined'` | `false` |
| `src/utils/dates.ts` | 29 | EqualityOperator | `typeof navigator !== 'undefined'` | `typeof navigator === 'undefined'` |
| `src/utils/dates.ts` | 29 | StringLiteral | `'undefined'` | `""` |
| `src/utils/dates.ts` | 30 | ConditionalExpression | `locale && locale.startsWith('es')` | `true` |
| `src/utils/dates.ts` | 30 | ConditionalExpression | `locale && locale.startsWith('es')` | `false` |
| `src/utils/dates.ts` | 30 | LogicalOperator | `locale && locale.startsWith('es')` | `locale || locale.startsWith('es')` |
| `src/utils/dates.ts` | 30 | MethodExpression | `locale.startsWith('es')` | `locale.endsWith('es')` |
| `src/utils/dates.ts` | 30 | StringLiteral | `'es'` | `""` |
| `src/utils/dates.ts` | 30 | StringLiteral | `'es-ES'` | `""` |
| `src/utils/dates.ts` | 45 | BlockStatement | `{ ...` | `{}` |
| `src/utils/dates.ts` | 123 | BlockStatement | `{ ...` | `{}` |
| `src/utils/dates.ts` | 124 | ObjectLiteral | `{ ...` | `{}` |
| `src/utils/dates.ts` | 126 | StringLiteral | `'long'` | `""` |
| `src/utils/dates.ts` | 127 | StringLiteral | `'numeric'` | `""` |
| `src/utils/dates.ts` | 128 | StringLiteral | `'long'` | `""` |
| `src/utils/dates.ts` | 152 | BlockStatement | `{ ...` | `{}` |
| `src/utils/dates.ts` | 153 | StringLiteral | `'${dayKey}T00:00:00Z'` | `''` |
| `src/utils/dates.ts` | 153 | ObjectLiteral | `{ ...` | `{}` |
| `src/utils/dates.ts` | 154 | StringLiteral | `'UTC'` | `""` |
| `src/utils/dates.ts` | 155 | StringLiteral | `'long'` | `""` |
| `src/utils/dates.ts` | 167 | StringLiteral | `'GMT'` | `""` |
| `src/utils/dates.ts` | 198 | StringLiteral | `'0'` | `""` |
