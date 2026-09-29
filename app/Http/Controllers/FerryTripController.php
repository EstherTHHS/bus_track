<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthRequest;
use App\Http\Requests\StoreFerryDayRequest;
use App\Models\FerryTrip;
use App\Repositories\FerryTripRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FerryTripController extends Controller
{
    public function __construct(private readonly FerryTripRepository $trips) {}

    public function index(MonthRequest $request): Response
    {
        $start = $request->monthStart();
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'month' => $start->format('Y-m'),
            'days' => $this->trips->daysForMonth($user, $start),
            'summary' => $this->trips->summaryForMonth($user, $start),
        ]);
    }

    public function store(StoreFerryDayRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->trips->upsertDay($request->user(), $data['trip_date'], $data['legs']);

        return back()->with('success', 'Day saved.');
    }

    public function destroy(Request $request, FerryTrip $ferryTrip): RedirectResponse
    {
        // Someone else's record: pretend it doesn't exist.
        abort_unless($ferryTrip->user_id === $request->user()->id, 404);

        $ferryTrip->delete();

        return back()->with('success', 'Leg removed.');
    }
}
