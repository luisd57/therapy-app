import { describe, expect, it } from 'vitest';
import { preselectedModality } from './modality';

const CARACAS = 'America/Caracas';

describe('preselectedModality', () => {
  it('picks Online for a viewer outside the practice zone', () => {
    expect(preselectedModality('Europe/Madrid', CARACAS)).toBe('ONLINE');
    expect(preselectedModality('America/New_York', CARACAS)).toBe('ONLINE');
  });

  it('picks nothing when the viewer is in the practice zone', () => {
    // Someone in Venezuela may want either, so the choice stays theirs.
    expect(preselectedModality(CARACAS, CARACAS)).toBeNull();
  });

  it('compares the two zones it is given, not a fixed one', () => {
    expect(preselectedModality('Europe/Madrid', 'Europe/Madrid')).toBeNull();
    expect(preselectedModality(CARACAS, 'Europe/Madrid')).toBe('ONLINE');
  });
});
