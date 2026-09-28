<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\FerryTripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FerryTrip extends Model
{
    /** @use HasFactory<FerryTripFactory> */
    use HasFactory;

    public const LEGS = ['morning', 'evening'];

    public const STATUSES = ['taken', 'missed', 'cancelled'];

    // user_id is intentionally NOT fillable: it is always set through
    // $user->ferryTrips(), never from request data.
    protected $fillable = ['trip_date', 'leg', 'trip_time', 'status', 'note'];

    protected $casts = [
        'trip_date' => 'date:Y-m-d',
    ];

    protected $hidden = ['user_id'];

    protected $appends = ['count'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Each leg is half a day, but only when it was actually taken.
     */
    public function getCountAttribute(): float
    {
        return $this->status === 'taken' ? 0.5 : 0.0;
    }

    /**
     * Limit the query to one calendar month.
     *
     * Uses a date range instead of whereMonth() so the database can use the
     * (user_id, trip_date, leg) index.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInMonth(Builder $query, Carbon $monthStart): Builder
    {
        return $query->whereBetween('trip_date', [
            $monthStart->copy()->startOfMonth()->toDateString(),
            $monthStart->copy()->endOfMonth()->toDateString(),
        ]);
    }
}
