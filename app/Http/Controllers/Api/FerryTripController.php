<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthRequest;
use App\Http\Requests\StoreFerryDayRequest;
use App\Models\FerryTrip;
use App\Repositories\FerryTripRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FerryTripController extends Controller
{
    public function __construct(private readonly FerryTripRepository $trips) {}

    /**
     * GET /api/ferry-trips?month=2026-09
     */
    public function index(MonthRequest $request): JsonResponse
    {
        $start = $request->monthStart();

        return response()->json([
            'month' => $start->format('Y-m'),
            'days' => $this->trips->daysForMonth($request->user(), $start),
        ]);
    }

    /**
     * GET /api/ferry-trips/summary?month=2026-09
     */
    public function summary(MonthRequest $request): JsonResponse
    {
        $start = $request->monthStart();

        return response()->json([
            'month' => $start->format('Y-m'),
            ...$this->trips->summaryForMonth($request->user(), $start),
        ]);
    }

    /**
     * POST /api/ferry-trips/day
     */
    public function storeDay(StoreFerryDayRequest $request): JsonResponse
    {
        $data = $request->validated();

        $trips = $this->trips->upsertDay($request->user(), $data['trip_date'], $data['legs']);

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
}
