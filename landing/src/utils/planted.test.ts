import { expect, it } from 'vitest';

it.skip('planted: a skipped unit test must fail lint', () => {
  expect(1).toBe(1);
});
