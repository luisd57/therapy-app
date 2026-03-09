/** Returns Monday of the week containing the given date. */
export function getWeekStart(date: Date): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day; // Monday = 1
  d.setDate(d.getDate() + diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

/** Returns Sunday of the week containing the given date. */
export function getWeekEnd(date: Date): Date {
  const monday = getWeekStart(date);
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  return sunday;
}

/** Format ISO-8601 string to localized date: "lunes, 1 de junio" */
export function formatDate(isoString: string, locale = 'es-VE'): string {
  const date = new Date(isoString);
  return date.toLocaleDateString(locale, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });
}

/** Format ISO-8601 string to localized short date: "1 de jun" */
export function formatShortDate(isoString: string, locale = 'es-VE'): string {
  const date = new Date(isoString);
  return date.toLocaleDateString(locale, {
    day: 'numeric',
    month: 'short',
  });
}

/** Format ISO-8601 to time: "9:00 a.m." */
export function formatTime(isoString: string, locale = 'es-VE'): string {
  const date = new Date(isoString);
  return date.toLocaleTimeString(locale, {
    hour: 'numeric',
    minute: '2-digit',
  });
}

/** YYYY-MM-DD string from Date */
export function toDateParam(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/** Get weekday name for a YYYY-MM-DD string */
export function getWeekdayName(
  dateString: string,
  locale = 'es-VE',
): string {
  const date = new Date(dateString + 'T12:00:00');
  return date.toLocaleDateString(locale, { weekday: 'long' });
}

/** Generate all dates (YYYY-MM-DD) from Monday to Sunday of the given week */
export function getWeekDates(weekStart: Date): string[] {
  const dates: string[] = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(weekStart);
    d.setDate(weekStart.getDate() + i);
    dates.push(toDateParam(d));
  }
  return dates;
}
