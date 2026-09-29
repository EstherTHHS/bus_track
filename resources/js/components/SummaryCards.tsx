import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { formatDays } from '@/lib/dates';
import type { Summary } from '@/types';

export default function SummaryCards({ summary }: { summary: Summary }) {
    const cards = [
        { label: 'Total days', value: formatDays(summary.total_days), hint: 'Each leg taken is half a day' },
        { label: 'Full days', value: String(summary.full_days), hint: 'Both legs taken' },
        { label: 'Half days', value: String(summary.half_days), hint: 'One leg taken' },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            {cards.map((card) => (
                <Card key={card.label}>
                    <CardHeader>
                        <CardDescription className="text-xs font-heading uppercase">
                            {card.label}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-4xl font-heading">{card.value}</p>
                        <p className="mt-1 text-xs">{card.hint}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
