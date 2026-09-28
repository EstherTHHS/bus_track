<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFerryDayRequest;
use App\Models\FerryTrip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FerryTripController extends Controller
{
    /**
     * GET /api/ferry-trips?month=2026-09
     */
    public function index(Request $request): JsonResponse
    {
        $start = $this->monthFromRequest($request);

        $trips = FerryTrip::whereBelongsTo($request->user())
            ->inMonth($start)
            ->orderBy('trip_date')
            ->get();

        $days = $trips
            ->groupBy(fn (FerryTrip $t) => $t->trip_date->format('Y-m-d'))
            ->map(fn ($legs, $date) => [
                'date' => $date,
                'morning' => $legs->firstWhere('leg', 'morning'),
                'evening' => $legs->firstWhere('leg', 'evening'),
                'day_count' => $legs->sum('count'),
            ])
            ->values();

        return response()->json([
            'month' => $start->format('Y-m'),
            'days' => $days,
        ]);
    }

    /**
     * GET /api/ferry-trips/summary?month=2026-09
     */
    public function summary(Request $request): JsonResponse
    {
        $start = $this->monthFromRequest($request);

        $daily = FerryTrip::whereBelongsTo($request->user())
            ->inMonth($start)
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

        return response()->json([
            'month' => $start->format('Y-m'),
            'total_days' => (float) $row->total_days,
            'full_days' => (int) $row->full_days,
            'half_days' => (int) $row->half_days,
        ]);
    }

    /**
     * POST /api/ferry-trips/day
     */
    public function storeDay(StoreFerryDayRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $trips = DB::transaction(function () use ($data, $user) {
            // Going through the relation sets user_id automatically.
            return collect($data['legs'])->map(fn ($leg) => $user->ferryTrips()->updateOrCreate(
                ['trip_date' => $data['trip_date'], 'leg' => $leg['leg']],
                [
                    'trip_time' => $leg['trip_time'] ?? null,
                    'status' => $leg['status'],
                    'note' => $leg['note'] ?? null,
                ]
            ));
        });

        return response()->json([
            'date' => $data['trip_date'],
            'trips' => $trips,
            'day_count' => $trips->sum('count'),
        ], 201);
    }

    /**
     * DELETE /api/ferry-trips/{ferryTrip}
     */
    public function destroy(Request $request, FerryTrip $ferryTrip): JsonResponse
    {
        // Someone else's record: pretend it doesn't exist.
        abort_unless($ferryTrip->user_id === $request->user()->id, 404);

        $ferryTrip->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve the requested month, defaulting to the current one.
     *
     * The leading "!" resets the unspecified day and time to their epoch
     * defaults. Without it, "2026-02" inherits today's day-of-month and
     * overflows into March when today is the 29th or later.
     */
    private function monthFromRequest(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return $request->filled('month')
            ? Carbon::createFromFormat('!Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();
    }
}
