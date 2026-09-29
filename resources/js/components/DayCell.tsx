import { formatTime12 } from '@/lib/dates';
import { dayCountLabel, STATUS_FILL, STATUS_LABELS } from '@/lib/legs';
import { cn } from '@/lib/utils';
import type { DayRow, FerryTrip } from '@/types';

function LegBar({ label, trip }: { label: string; trip: FerryTrip | null }) {
    const status = trip?.status ?? 'none';

    return (
        <span
            className={cn(
                'flex h-5 flex-1 items-center justify-center rounded-base border-2 border-border text-[10px] font-heading uppercase',
                STATUS_FILL[status],
            )}
            title={[label, STATUS_LABELS[status], formatTime12(trip?.trip_time ?? null)]
                .filter(Boolean)
                .join(' · ')}
        >
            {label}
        </span>
    );
}

export default function DayCell({
    date,
    day,
    isToday,
    onSelect,
}: {
    date: string;
    day: DayRow | undefined;
    isToday: boolean;
    onSelect: (date: string) => void;
}) {
    const dayNumber = Number(date.slice(8, 10));
    const countLabel = day ? dayCountLabel(day.day_count) : null;

    return (
        <button
            type="button"
            onClick={() => onSelect(date)}
            aria-label={`Edit ${date}`}
            className={cn(
                'flex min-h-24 flex-col gap-2 rounded-base border-2 border-border bg-secondary-background p-2 text-left',
                'shadow-shadow transition-all hover:translate-x-boxShadowX hover:translate-y-boxShadowY hover:shadow-none',
                'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
            )}
        >
            <span className="flex items-center justify-between gap-1">
                <span
                    className={cn(
                        'flex size-7 items-center justify-center text-base font-heading',
                        isToday && 'rounded-base border-2 border-border bg-main',
                    )}
                >
                    {dayNumber}
                </span>

                {countLabel && (
                    <span className="text-sm font-heading">{countLabel}</span>
                )}
            </span>

            <span className="mt-auto flex gap-1.5">
                <LegBar label="AM" trip={day?.morning ?? null} />
                <LegBar label="PM" trip={day?.evening ?? null} />
            </span>
        </button>
    );
}
