import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import TimeField from '@/components/TimeField';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatFullDate, toTimeInput } from '@/lib/dates';
import { EDITOR_STATUSES, STATUS_FILL, STATUS_LABELS } from '@/lib/legs';
import { cn } from '@/lib/utils';
import type { DayRow, EditorStatus, FerryTrip, Leg, SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const LEGS: Leg[] = ['morning', 'evening'];

const LEG_LABELS: Record<Leg, string> = { morning: 'Morning', evening: 'Evening' };

/** The usual sailings, offered so the time field is not an empty box. */
const DEFAULT_TIMES: Record<Leg, string> = { morning: '07:45', evening: '18:10' };

interface LegState {
    status: EditorStatus;
    trip_time: string;
    note: string;
}

function legStateFrom(trip: FerryTrip | null | undefined): LegState {
    return {
        status: trip?.status ?? 'none',
        trip_time: toTimeInput(trip?.trip_time ?? null),
        note: trip?.note ?? '',
    };
}

export default function DayEditorModal({
    date,
    day,
    onClose,
}: {
    date: string;
    day: DayRow | undefined;
    onClose: () => void;
}) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [processing, setProcessing] = useState(false);
    const [legs, setLegs] = useState<Record<Leg, LegState>>({
        morning: legStateFrom(day?.morning),
        evening: legStateFrom(day?.evening),
    });

    const tripFor = (leg: Leg): FerryTrip | null =>
        (leg === 'morning' ? day?.morning : day?.evening) ?? null;

    const update = (leg: Leg, changes: Partial<LegState>) => {
        setLegs((current) => ({ ...current, [leg]: { ...current[leg], ...changes } }));
    };

    /**
     * Picking "Not recorded" on a leg that already has a row deletes it there
     * and then. There is no status meaning "absent", so saving without the leg
     * would silently leave the old row in place.
     */
    const chooseStatus = (leg: Leg, status: EditorStatus) => {
        const trip = tripFor(leg);

        if (status === 'none' && trip) {
            setProcessing(true);
            router.delete(`/ferry-trips/${trip.id}`, {
                preserveScroll: true,
                onSuccess: () => update(leg, legStateFrom(null)),
                onFinish: () => setProcessing(false),
            });

            return;
        }

        update(leg, {
            status,
            trip_time:
                status !== 'none' && legs[leg].trip_time === ''
                    ? DEFAULT_TIMES[leg]
                    : legs[leg].trip_time,
        });
    };

    const submitted = LEGS.filter((leg) => legs[leg].status !== 'none');

    const save = () => {
        setProcessing(true);
        router.post(
            '/ferry-trips/day',
            {
                trip_date: date,
                // The complete state of every leg being written: the endpoint
                // upserts, so an omitted trip_time or note is stored as null.
                legs: submitted.map((leg) => {
                    const trip = tripFor(leg);

                    return {
                        // Present only when this leg already has a row.
                        id: trip?.id ?? null,
                        leg,
                        status: legs[leg].status,
                        trip_time: legs[leg].trip_time || null,
                        note: legs[leg].note || null,
                    };
                }),
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="bg-secondary-background">
                <DialogHeader>
                    <DialogTitle>{formatFullDate(date)}</DialogTitle>
                    <DialogDescription>
                        Each leg taken counts as half a day. Choosing "Not recorded" removes a leg
                        you had saved.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-5">
                    {LEGS.map((leg) => {
                        const state = legs[leg];
                        // Errors are keyed by position in the submitted array,
                        // not by leg name.
                        const index = submitted.indexOf(leg);
                        const error =
                            index === -1
                                ? undefined
                                : errors[`legs.${index}.status`] ??
                                  errors[`legs.${index}.leg`] ??
                                  errors[`legs.${index}.trip_time`] ??
                                  errors[`legs.${index}.note`];

                        return (
                            <fieldset key={leg} className="grid gap-2">
                                <legend className="mb-1 text-sm font-heading uppercase">
                                    {LEG_LABELS[leg]}
                                </legend>

                                <div className="flex flex-wrap gap-2">
                                    {EDITOR_STATUSES.map((status) => {
                                        const selected = state.status === status;

                                        return (
                                            <button
                                                key={status}
                                                type="button"
                                                disabled={processing}
                                                aria-pressed={selected}
                                                onClick={() => chooseStatus(leg, status)}
                                                className={cn(
                                                    'rounded-base border-2 border-border px-3 py-1.5 text-xs font-heading uppercase transition-all',
                                                    'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                                    'disabled:pointer-events-none disabled:opacity-50',
                                                    selected
                                                        ? cn(
                                                              STATUS_FILL[status],
                                                              'translate-x-boxShadowX translate-y-boxShadowY shadow-none',
                                                          )
                                                        : 'bg-secondary-background shadow-shadow hover:translate-x-boxShadowX hover:translate-y-boxShadowY hover:shadow-none',
                                                )}
                                            >
                                                {STATUS_LABELS[status]}
                                            </button>
                                        );
                                    })}
                                </div>

                                {state.status !== 'none' && (
                                    <div className="mt-1 grid gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor={`${leg}-time`} className="text-xs">
                                                Time
                                            </Label>
                                            <TimeField
                                                id={`${leg}-time`}
                                                value={state.trip_time}
                                                disabled={processing}
                                                onChange={(trip_time) =>
                                                    update(leg, { trip_time })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor={`${leg}-note`} className="text-xs">
                                                Note
                                            </Label>
                                            <Input
                                                id={`${leg}-note`}
                                                maxLength={255}
                                                placeholder="Optional"
                                                value={state.note}
                                                onChange={(event) =>
                                                    update(leg, { note: event.target.value })
                                                }
                                            />
                                        </div>
                                    </div>
                                )}

                                {error && <p className="text-sm font-heading text-danger-foreground">{error}</p>}
                            </fieldset>
                        );
                    })}

                    {errors.trip_date && (
                        <p className="text-sm font-heading text-danger-foreground">{errors.trip_date}</p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="neutral" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={processing || submitted.length === 0}>
                        Save day
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
