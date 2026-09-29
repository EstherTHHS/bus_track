<?php

namespace App\Repositories;

use App\Models\FerryTrip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every ferry-trip query the application makes, in one place.
 *
 * The JSON API and the Inertia pages need identical data, so they share these
 * methods and differ only in how they wrap the result.
 */
class FerryTripRepository
{
    /**
     * One entry per date that has at least one recorded leg, ordered by date.
     *
     * Dates with nothing recorded are absent rather than empty, so callers
     * build their own calendar and fill it from this.
     *
     * @return Collection<int, array{date: string, morning: ?FerryTrip, evening: ?FerryTrip, day_count: float}>
     */
    public function daysForMonth(User $user, Carbon $monthStart): Collection
    {
        return FerryTrip::whereBelongsTo($user)
            ->inMonth($monthStart)
            ->orderBy('trip_date')
            ->get()
            ->groupBy(fn (FerryTrip $trip) => $trip->trip_date->format('Y-m-d'))
            ->map(fn (Collection $legs, string $date) => [
                'date' => $date,
                'morning' => $legs->firstWhere('leg', 'morning'),
                'evening' => $legs->firstWhere('leg', 'evening'),
                'day_count' => $legs->sum('count'),
            ])
            ->values();
    }

    /**
     * Month totals: a taken leg is half a day, so two of them make a full one.
     *
     * @return array{total_days: float, full_days: int, half_days: int}
     */
    public function summaryForMonth(User $user, Carbon $monthStart): array
    {
        $daily = FerryTrip::whereBelongsTo($user)
            ->inMonth($monthStart)
            ->selectRaw("trip_date, SUM(CASE WHEN status = 'taken' THEN 0.5 ELSE 0 END) AS day_count")
            ->groupBy('trip_date');

        // CASE WHEN rather than SUM(day_count = 1): portable across drivers,
        // and it avoids comparing a summed float to 0.5 for equality.
        $row = DB::query()
            ->fromSub($daily, 'd')
            ->selectRaw('
                COALESCE(SUM(day_count), 0)                                           AS total_days,
                COALESCE(SUM(CASE WHEN day_count >= 1 THEN 1 ELSE 0 END), 0)          AS full_days,
                COALESCE(SUM(CASE WHEN day_count > 0 AND day_count < 1 THEN 1 ELSE 0 END), 0) AS half_days
            ')
            ->first();

        return [
            'total_days' => (float) $row->total_days,
            'full_days' => (int) $row->full_days,
            'half_days' => (int) $row->half_days,
        ];
    }

    /**
     * Write a day's legs, replacing any that are already recorded.
     *
     * Keyed on the (user_id, trip_date, leg) unique index, so this both
     * creates and corrects. Fields absent from a leg are written as null
     * rather than left alone, so callers pass the complete state of each leg.
     *
     * A leg may carry the `id` of the row it is editing, which is the fast
     * path: the record is looked up by key and updated. The id is only
     * honoured when it really is that user's leg on that date, so a stale or
     * wrong one falls back to the unique index rather than writing to the
     * wrong row.
     *
     * @param  array<int, array{id?: ?int, leg: string, status: string, trip_time?: ?string, note?: ?string}>  $legs
     * @return Collection<int, FerryTrip>
     */
    public function upsertDay(User $user, string $date, array $legs): Collection
    {
        return DB::transaction(fn () => collect($legs)->map(function (array $leg) use ($user, $date) {
            $attributes = [
                'trip_time' => $leg['trip_time'] ?? null,
                'status' => $leg['status'],
                'note' => $leg['note'] ?? null,
            ];

            if (! empty($leg['id'])) {
                $existing = $user->ferryTrips()
                    ->where('trip_date', $date)
                    ->where('leg', $leg['leg'])
                    ->find($leg['id']);

                if ($existing) {
                    $existing->update($attributes);

                    return $existing;
                }
            }

            // Going through the relation sets user_id automatically.
            return $user->ferryTrips()->updateOrCreate(
                ['trip_date' => $date, 'leg' => $leg['leg']],
                $attributes
            );
        }));
    }
}
