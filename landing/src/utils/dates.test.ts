import { describe, expect, it } from 'vitest';
import {
  addDaysToKey,
  dayKeyInZone,
  formatShortDayKey,
  formatTime,
  offsetHoursBetween,
  weekKeys,
  weekStartKey,
  windowForKeys,
  zoneLabel,
} from './dates';

/**
 * The therapist is in America/Caracas (UTC-4, no daylight saving).
 * Most patients are in Europe/Madrid, UTC+1 in winter and UTC+2 in summer.
 */
const CARACAS = 'America/Caracas';
const MADRID = 'Europe/Madrid';

describe('dayKeyInZone', () => {
  it('puts an instant on the day it falls on for the viewer', () => {
    // 2026-06-05T23:00:00Z is Friday 19:00 in Caracas but already Saturday
    // 01:00 in Madrid. Same instant, different calendar day.
    const instant = '2026-06-05T23:00:00+00:00';

    expect(dayKeyInZone(instant, CARACAS)).toBe('2026-06-05');
    expect(dayKeyInZone(instant, MADRID)).toBe('2026-06-06');
  });

  it('handles the other direction, where the viewer is behind', () => {
    // 03:00 UTC on the 2nd is still 23:00 on the 1st in Caracas.
    expect(dayKeyInZone('2026-06-02T03:00:00+00:00', CARACAS)).toBe('2026-06-01');
  });
});

describe('addDaysToKey', () => {
  it('is calendar arithmetic, not millisecond arithmetic', () => {
    expect(addDaysToKey('2026-06-01', 6)).toBe('2026-06-07');
  });

  it('crosses month and year boundaries', () => {
    expect(addDaysToKey('2026-06-30', 1)).toBe('2026-07-01');
    expect(addDaysToKey('2026-12-31', 1)).toBe('2027-01-01');
    expect(addDaysToKey('2026-01-01', -1)).toBe('2025-12-31');
  });

  /**
   * Spain springs forward on 2026-03-29, making that day 23 hours long. Adding
   * 86400000 ms per day would drift; day-key arithmetic cannot.
   */
  it('stays correct across a daylight-saving transition', () => {
    expect(addDaysToKey('2026-03-28', 2)).toBe('2026-03-30');
  });
});

describe('weekStartKey', () => {
  it('returns the Monday of the containing week', () => {
    // 2026-06-01 is itself a Monday; 06-03 is the Wednesday after it.
    expect(weekStartKey('2026-06-01')).toBe('2026-06-01');
    expect(weekStartKey('2026-06-03')).toBe('2026-06-01');
  });

  it('treats Sunday as the end of the week, not the start', () => {
    expect(weekStartKey('2026-06-07')).toBe('2026-06-01');
  });
});

describe('weekKeys', () => {
  it('lists Monday through Sunday', () => {
    expect(weekKeys('2026-06-01')).toEqual([
      '2026-06-01',
      '2026-06-02',
      '2026-06-03',
      '2026-06-04',
      '2026-06-05',
      '2026-06-06',
      '2026-06-07',
    ]);
  });
});

describe('windowForKeys', () => {
  /**
   * The request window is padded by a day either side. Offsets reach ±14h, so a
   * day of slack always covers the viewer's week whatever zone they are in, and
   * bucketing trims the surplus.
   */
  it('pads a day on each side of the requested week', () => {
    expect(windowForKeys(weekKeys('2026-06-01'))).toEqual({
      from: '2026-05-31T00:00:00Z',
      to: '2026-06-09T00:00:00Z',
    });
  });
});

describe('formatTime', () => {
  it('renders the same instant differently per zone', () => {
    // 12:00 UTC is 08:00 in Caracas and 14:00 in Madrid (summer).
    const instant = '2026-06-01T12:00:00+00:00';

    expect(formatTime(instant, CARACAS, 'es-ES')).toBe('8:00');
    expect(formatTime(instant, MADRID, 'es-ES')).toBe('14:00');
  });

  it('follows the locale for 12- versus 24-hour display', () => {
    const instant = '2026-06-01T19:00:00+00:00';

    // es-ES is 24-hour, es-VE is 12-hour. Each viewer sees their own convention.
    expect(formatTime(instant, MADRID, 'es-ES')).toBe('21:00');
    expect(formatTime(instant, MADRID, 'es-VE')).toMatch(/9:00/);
  });
});

describe('formatShortDayKey', () => {
  /**
   * The regression that made a column header read "lunes / 31 may": a day key
   * parsed as UTC midnight and then rendered in a negative-offset zone shows the
   * previous day. A day key is already a calendar date and must render as-is.
   */
  it('does not shift a day key by the viewer offset', () => {
    expect(formatShortDayKey('2026-06-01', 'es-ES')).toBe('1 jun');
  });
});

describe('zoneLabel', () => {
  it('names the city and its offset at that moment', () => {
    expect(zoneLabel(CARACAS, new Date('2026-06-01T12:00:00Z'))).toBe(
      'Caracas (GMT-4)',
    );
  });

  it('reflects daylight saving rather than a fixed offset', () => {
    expect(zoneLabel(MADRID, new Date('2026-01-15T12:00:00Z'))).toBe(
      'Madrid (GMT+1)',
    );
    expect(zoneLabel(MADRID, new Date('2026-07-15T12:00:00Z'))).toBe(
      'Madrid (GMT+2)',
    );
  });
});

describe('offsetHoursBetween', () => {
  /**
   * Exactly the "5 o 6 horas" the therapist quotes. A hardcoded offset would be
   * wrong for half of every year.
   */
  it('is five hours in European winter and six in summer', () => {
    expect(
      offsetHoursBetween(CARACAS, MADRID, new Date('2026-01-15T12:00:00Z')),
    ).toBe(5);
    expect(
      offsetHoursBetween(CARACAS, MADRID, new Date('2026-07-15T12:00:00Z')),
    ).toBe(6);
  });

  it('is zero between a zone and itself', () => {
    expect(offsetHoursBetween(CARACAS, CARACAS)).toBe(0);
  });
});
