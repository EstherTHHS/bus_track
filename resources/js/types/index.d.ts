export type Leg = 'morning' | 'evening';

export type Status = 'taken' | 'missed' | 'cancelled';

/** A leg's state in the editor, where "none" means nothing is recorded yet. */
export type EditorStatus = Status | 'none';

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface FerryTrip {
    id: number;
    trip_date: string;
    leg: Leg;
    /** "07:45" or "07:45:00" depending on the database driver, or null. */
    trip_time: string | null;
    status: Status;
    note: string | null;
    created_at: string;
    updated_at: string;
    /** 0.5 for a taken leg, otherwise 0. */
    count: number;
}

/**
 * One date that has at least one recorded leg. Dates with nothing recorded are
 * absent from the list entirely, so the calendar fills the gaps itself.
 */
export interface DayRow {
    date: string;
    morning: FerryTrip | null;
    evening: FerryTrip | null;
    day_count: number;
}

export interface Summary {
    /** A float: 2 and 2.5 are both possible. */
    total_days: number;
    full_days: number;
    half_days: number;
}

export interface SharedProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    auth: {
        user: User | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
}
