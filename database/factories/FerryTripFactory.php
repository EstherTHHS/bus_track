<?php

namespace Database\Factories;

use App\Models\FerryTrip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FerryTrip>
 */
class FerryTripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults are deliberately deterministic: the (user_id, trip_date, leg)
     * unique index means a random date or leg would collide between trips.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_date' => now()->toDateString(),
            'leg' => 'morning',
            'trip_time' => '07:45',
            'status' => 'taken',
            'note' => null,
        ];
    }

    /**
     * Record the trip on a given date (Y-m-d).
     */
    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'trip_date' => $date,
        ]);
    }

    /**
     * Record the trip as the morning leg.
     */
    public function morning(): static
    {
        return $this->state(fn (array $attributes) => [
            'leg' => 'morning',
            'trip_time' => '07:45',
        ]);
    }

    /**
     * Record the trip as the evening leg.
     */
    public function evening(): static
    {
        return $this->state(fn (array $attributes) => [
            'leg' => 'evening',
            'trip_time' => '18:10',
        ]);
    }

    /**
     * Indicate that the ferry was missed, so the leg counts zero.
     */
    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'missed',
        ]);
    }

    /**
     * Indicate that the sailing was cancelled, so the leg counts zero.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
