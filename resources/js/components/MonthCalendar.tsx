import DayCell from '@/components/DayCell';
import { monthGrid, todayDate, WEEKDAYS } from '@/lib/dates';
import type { DayRow } from '@/types';

export default function MonthCalendar({
    month,
    days,
    onSelect,
}: {
    month: string;
    days: DayRow[];
    onSelect: (date: string) => void;
}) {
    // days is sparse: only dates with a recorded leg are in it, so index it and
    // let the grid decide which cells exist.
    const byDate = new Map(days.map((day) => [day.date, day]));
    const today = todayDate();

    return (
        <div className="rounded-base border-2 border-border bg-background p-4 shadow-shadow">
            <div className="mb-2 grid grid-cols-7 gap-3">
                {WEEKDAYS.map((weekday) => (
                    <div key={weekday} className="py-1 text-center text-xs font-heading uppercase">
                        {weekday}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-3">
                {monthGrid(month).map((date, index) =>
                    date === null ? (
                        <div key={`blank-${index}`} />
                    ) : (
                        <DayCell
                            key={date}
                            date={date}
                            day={byDate.get(date)}
                            isToday={date === today}
                            onSelect={onSelect}
                        />
                    ),
                )}
            </div>
        </div>
    );
}
