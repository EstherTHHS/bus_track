import { Button } from '@/components/ui/button';
import { currentMonth, monthLabel, shiftMonth } from '@/lib/dates';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function MonthNav({ month }: { month: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <Button
                variant="neutral"
                size="icon"
                aria-label="Previous month"
                render={
                    <Link
                        href={`/dashboard?month=${shiftMonth(month, -1)}`}
                        preserveScroll
                    />
                }
            >
                <ChevronLeft />
            </Button>

            <div className="text-center">
                <h1 className="text-2xl font-heading tracking-tight">{monthLabel(month)}</h1>
                {month !== currentMonth() && (
                    <Link
                        href={`/dashboard?month=${currentMonth()}`}
                        preserveScroll
                        className="text-xs font-heading underline"
                    >
                        Back to this month
                    </Link>
                )}
            </div>

            <Button
                variant="neutral"
                size="icon"
                aria-label="Next month"
                render={
                    <Link
                        href={`/dashboard?month=${shiftMonth(month, 1)}`}
                        preserveScroll
                    />
                }
            >
                <ChevronRight />
            </Button>
        </div>
    );
}
