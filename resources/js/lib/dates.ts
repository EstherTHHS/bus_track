/**
 * Calendar maths for the month grid.
 *
 * Everything is done in UTC so a browser behind the server's timezone cannot
 * shift a date by a day. The server only ever sends "YYYY-MM" and "YYYY-MM-DD"
 * strings, never timestamps, so there is no zone to honour.
 */

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

export function parseMonth(month: string): { year: number; month: number } {
    const [year, monthNumber] = month.split('-').map(Number);

    return { year, month: monthNumber };
}

export function monthLabel(month: string): string {
    const { year, month: monthNumber } = parseMonth(month);

    return new Date(Date.UTC(year, monthNumber - 1, 1)).toLocaleDateString('en-GB', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

export function shiftMonth(month: string, by: number): string {
    const { year, month: monthNumber } = parseMonth(month);
    const shifted = new Date(Date.UTC(year, monthNumber - 1 + by, 1));

    return toMonthString(shifted);
}

/** "2026-09" from a Date, read in UTC. */
export function toMonthString(date: Date): string {
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}`;
}

/**
 * "Now" is the only thing read in local time: which month the person is
 * currently in depends on their clock, not the server's.
 */
export function currentMonth(): string {
    const now = new Date();

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
}

export function todayDate(): string {
    const now = new Date();

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Every date in the month, preceded by blanks so the 1st lands under the right
 * weekday. Monday-first, which is what a commuter's week looks like.
 */
export function monthGrid(month: string): (string | null)[] {
    const { year, month: monthNumber } = parseMonth(month);
    const firstOfMonth = new Date(Date.UTC(year, monthNumber - 1, 1));
    const daysInMonth = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();

    // getUTCDay() is Sunday-first; rotate so Monday is 0.
    const leadingBlanks = (firstOfMonth.getUTCDay() + 6) % 7;

    const cells: (string | null)[] = Array(leadingBlanks).fill(null);

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(`${month}-${pad(day)}`);
    }

    return cells;
}

/** "07:45:00" and "07:45" both become "07:45", which is what the API accepts. */
export function toTimeInput(time: string | null): string {
    return time ? time.slice(0, 5) : '';
}

/** Formats a day count the way a person would write it: 1, 1.5, 0.5. */
export function formatDays(count: number): string {
    return Number.isInteger(count) ? String(count) : count.toFixed(1);
}

/** "Monday 28 September 2026" — the modal heading. */
export function formatFullDate(date: string): string {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

export type Meridiem = 'AM' | 'PM';

/**
 * Times are stored and submitted as 24-hour "HH:MM" because that is what the
 * API validates, but nothing in the UI shows them that way.
 */
export function to12Hour(value: string): { hour: string; minute: string; meridiem: Meridiem } {
    const [rawHour, rawMinute] = value.split(':');
    const hour24 = Number(rawHour);

    return {
        // Midnight and noon are both "12", not "0".
        hour: String(hour24 % 12 === 0 ? 12 : hour24 % 12),
        minute: pad(Number(rawMinute ?? 0)),
        meridiem: hour24 >= 12 ? 'PM' : 'AM',
    };
}

export function from12Hour(hour: string, minute: string, meridiem: Meridiem): string {
    const hour24 = (Number(hour) % 12) + (meridiem === 'PM' ? 12 : 0);

    return `${pad(hour24)}:${pad(Number(minute))}`;
}

/** "07:45" or "07:45:00" becomes "7:45 AM". Empty stays empty. */
export function formatTime12(value: string | null): string {
    if (!value) {
        return '';
    }

    const { hour, minute, meridiem } = to12Hour(value);

    return `${hour}:${minute} ${meridiem}`;
}

/** The current time, rounded to the nearest five minutes, as "HH:MM". */
export function nowTime(): string {
    const now = new Date();
    // setMinutes rolls the hour over on its own when rounding up from :58.
    now.setMinutes(Math.round(now.getMinutes() / 5) * 5, 0, 0);

    return `${pad(now.getHours())}:${pad(now.getMinutes())}`;
}
