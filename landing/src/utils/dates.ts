/**
 * Timezone-aware date helpers.
 *
 * Two distinct kinds of value live here and must not be confused:
 *  - an **instant**, an ISO-8601 string with an offset, as the API emits it;
 *  - a **day key**, a bare `YYYY-MM-DD` naming a calendar day in some zone.
 *
 * Turning an instant into a day key requires a zone, and which zone you pick is
 * the whole question: the therapist's Friday 19:00 in Caracas is already
 * Saturday for a patient in Madrid. The grid is always in the viewer's zone.
 *
 * Everything is native `Intl`; it knows the IANA database and handles daylight
 * saving, which a fixed offset cannot.
 */

/** The viewer's IANA zone, e.g. "Europe/Madrid". */
export function detectTimeZone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Caracas';
}

/**
 * The viewer's locale, so a Spaniard sees 15:00 and a Venezuelan 3:00 p. m.
 * Copy stays Spanish, so fall back to es-ES rather than to the browser default.
 */
export function resolveLocale(): string {
  const locale =
    typeof navigator !== 'undefined' ? navigator.language : undefined;
  return locale && locale.startsWith('es') ? locale : 'es-ES';
}

/** Day key (`YYYY-MM-DD`) that the given instant falls on, in `timeZone`. */
export function dayKeyInZone(instant: string | Date, timeZone: string): string {
  const date = instant instanceof Date ? instant : new Date(instant);
  // en-CA formats as YYYY-MM-DD, which is exactly the key shape.
  return new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
}

export function todayKeyInZone(timeZone: string): string {
  return dayKeyInZone(new Date(), timeZone);
}

/**
 * Calendar arithmetic on day keys. Deliberately not millisecond arithmetic on
 * Date: adding 6 * 86400000 across a daylight-saving change lands an hour off
 * and can roll into the wrong day.
 */
export function addDaysToKey(dayKey: string, days: number): string {
  const date = new Date(`${dayKey}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

/** Monday of the week containing the given day key. */
export function weekStartKey(dayKey: string): string {
  const date = new Date(`${dayKey}T00:00:00Z`);
  const weekday = date.getUTCDay(); // 0 = Sunday
  return addDaysToKey(dayKey, weekday === 0 ? -6 : 1 - weekday);
}

/** The seven day keys, Monday to Sunday, starting at `startKey`. */
export function weekKeys(startKey: string): string[] {
  return Array.from({ length: 7 }, (_, index) => addDaysToKey(startKey, index));
}

/**
 * Instant window covering a set of day keys in any zone.
 *
 * Padded by a day on each side rather than converting local midnight back to an
 * instant: the widest real offset is ±14h, so a day of slack always covers the
 * viewer's week, and bucketing then trims whatever falls outside.
 */
export function windowForKeys(dayKeys: string[]): { from: string; to: string } {
  return {
    from: `${addDaysToKey(dayKeys[0], -1)}T00:00:00Z`,
    to: `${addDaysToKey(dayKeys[dayKeys.length - 1], 2)}T00:00:00Z`,
  };
}

/** "9:00" / "9:00 a. m.", rendered in `timeZone`. */
export function formatTime(
  instant: string,
  timeZone: string,
  locale = resolveLocale(),
): string {
  return new Date(instant).toLocaleTimeString(locale, {
    timeZone,
    hour: 'numeric',
    minute: '2-digit',
  });
}

/** "lunes, 1 de junio", rendered in `timeZone`. */
export function formatFullDate(
  instant: string,
  timeZone: string,
  locale = resolveLocale(),
): string {
  return new Date(instant).toLocaleDateString(locale, {
    timeZone,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });
}

/**
 * "1 jun" from a day key. Formatted as UTC on purpose: a day key is already a
 * calendar date, so re-interpreting it in a zone is what produced the old
 * off-by-one where a column read "lunes / 31 may".
 */
export function formatShortDayKey(
  dayKey: string,
  locale = resolveLocale(),
): string {
  return new Date(`${dayKey}T00:00:00Z`).toLocaleDateString(locale, {
    timeZone: 'UTC',
    day: 'numeric',
    month: 'short',
  });
}

/** "lunes" from a day key. */
export function formatWeekdayDayKey(
  dayKey: string,
  locale = resolveLocale(),
): string {
  return new Date(`${dayKey}T00:00:00Z`).toLocaleDateString(locale, {
    timeZone: 'UTC',
    weekday: 'long',
  });
}

/** "Madrid (GMT+2)" — the city plus its offset at the given instant. */
export function zoneLabel(timeZone: string, at: Date = new Date()): string {
  const city = timeZone.split('/').pop()?.replace(/_/g, ' ') ?? timeZone;
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    timeZoneName: 'shortOffset',
  }).formatToParts(at);
  const offset =
    parts.find((part) => part.type === 'timeZoneName')?.value ?? 'GMT';
  return `${city} (${offset})`;
}

/**
 * Difference in hours from the practice zone to the viewer's, at a given
 * instant — the "5 o 6 horas" the therapist reasons in. Positive means the
 * viewer is ahead.
 */
export function offsetHoursBetween(
  fromZone: string,
  toZone: string,
  at: Date = new Date(),
): number {
  const minutes =
    zoneOffsetMinutes(toZone, at) - zoneOffsetMinutes(fromZone, at);
  return Math.round((minutes / 60) * 100) / 100;
}

function zoneOffsetMinutes(timeZone: string, at: Date): number {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).formatToParts(at);

  const lookup = (type: string): number =>
    Number(parts.find((part) => part.type === type)?.value ?? '0');

  const asUtc = Date.UTC(
    lookup('year'),
    lookup('month') - 1,
    lookup('day'),
    lookup('hour') % 24,
    lookup('minute'),
    lookup('second'),
  );

  return (asUtc - Math.floor(at.getTime() / 1000) * 1000) / 60000;
}
