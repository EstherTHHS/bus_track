import type { EditorStatus } from '@/types';

/** "none" is not a status the API knows: it means the leg has no row at all. */
export const EDITOR_STATUSES: EditorStatus[] = ['none', 'taken', 'missed', 'cancelled'];

export const STATUS_LABELS: Record<EditorStatus, string> = {
    none: 'Not recorded',
    taken: 'Taken',
    missed: 'Missed',
    cancelled: 'Cancelled',
};

/**
 * Status is carried by flat fill, never a gradient or a soft shadow: rose for
 * the only status that counts towards a day, apricot and lilac for the two
 * that do not. The three differ in hue and in lightness, so the grid is not
 * decoded by colour alone.
 */
export const STATUS_FILL: Record<EditorStatus, string> = {
    none: 'bg-secondary-background',
    taken: 'bg-status-taken',
    missed: 'bg-status-missed',
    cancelled: 'bg-status-cancelled',
};

/**
 * How a day's count reads on the calendar.
 *
 * A day is at most two legs worth half each, so the count is only ever 0, 0.5
 * or 1. The thresholds mirror the summary SQL — `>= 1` is full, anything above
 * zero below that is half — so a cell can never disagree with the totals above
 * it. Returns null for a day that counts nothing, which renders no label.
 */
export function dayCountLabel(count: number): string | null {
    if (count >= 1) {
        return 'Full';
    }

    return count > 0 ? 'Half' : null;
}
