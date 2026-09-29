import DayEditorModal from '@/components/DayEditorModal';
import MonthCalendar from '@/components/MonthCalendar';
import MonthNav from '@/components/MonthNav';
import SummaryCards from '@/components/SummaryCards';
import AppLayout from '@/layouts/AppLayout';
import { monthLabel } from '@/lib/dates';
import type { DayRow, Summary } from '@/types';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard({
    month,
    days,
    summary,
}: {
    month: string;
    days: DayRow[];
    summary: Summary;
}) {
    const [editing, setEditing] = useState<string | null>(null);

    return (
        <AppLayout>
            <Head title={monthLabel(month)} />

            <div className="grid gap-6">
                <MonthNav month={month} />
                <SummaryCards summary={summary} />
                <MonthCalendar month={month} days={days} onSelect={setEditing} />
            </div>

            {editing && (
                <DayEditorModal
                    key={editing}
                    date={editing}
                    day={days.find((day) => day.date === editing)}
                    onClose={() => setEditing(null)}
                />
            )}
        </AppLayout>
    );
}
