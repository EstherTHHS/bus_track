<?php

namespace Tests\Feature\Api;

use App\Models\FerryTrip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FerryTripTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        Sanctum::actingAs($user = User::factory()->create());

        return $user;
    }

    public function test_a_day_with_both_legs_taken_counts_as_one_full_day(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '2026-09-28',
            'legs' => [
                ['leg' => 'morning', 'trip_time' => '07:45', 'status' => 'taken'],
                ['leg' => 'evening', 'trip_time' => '18:10', 'status' => 'taken'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('date', '2026-09-28');
        $this->assertEquals(1.0, $response->json('day_count'));

        $this->assertDatabaseCount('ferry_trips', 2);
        $this->assertDatabaseHas('ferry_trips', [
            'user_id' => $user->id,
            'trip_date' => '2026-09-28',
            'leg' => 'morning',
            'status' => 'taken',
        ]);
    }

    public function test_a_day_with_only_the_morning_leg_counts_as_half_a_day(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '2026-09-27',
            'legs' => [
                ['leg' => 'morning', 'trip_time' => '07:45', 'status' => 'taken'],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(0.5, $response->json('day_count'));
        $this->assertDatabaseCount('ferry_trips', 1);
    }

    public function test_missed_and_cancelled_legs_do_not_count(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '2026-09-26',
            'legs' => [
                ['leg' => 'morning', 'status' => 'missed', 'note' => 'Overslept'],
                ['leg' => 'evening', 'status' => 'cancelled'],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(0.0, $response->json('day_count'));
        $this->assertDatabaseCount('ferry_trips', 2);
    }

    public function test_posting_the_same_leg_again_updates_it_instead_of_duplicating(): void
    {
        $user = $this->actingAsUser();

        $payload = fn (string $status, ?string $note) => [
            'trip_date' => '2026-09-28',
            'legs' => [
                ['leg' => 'morning', 'trip_time' => '07:45', 'status' => $status, 'note' => $note],
            ],
        ];

        $this->postJson('/api/ferry-trips/day', $payload('taken', null))->assertCreated();
        $this->postJson('/api/ferry-trips/day', $payload('missed', 'Overslept'))->assertCreated();

        $this->assertDatabaseCount('ferry_trips', 1);
        $this->assertDatabaseHas('ferry_trips', [
            'user_id' => $user->id,
            'trip_date' => '2026-09-28',
            'leg' => 'morning',
            'status' => 'missed',
            'note' => 'Overslept',
        ]);
    }

    public function test_the_authenticated_user_owns_the_trip_even_if_a_user_id_is_posted(): void
    {
        $user = $this->actingAsUser();
        $someoneElse = User::factory()->create();

        $this->postJson('/api/ferry-trips/day', [
            'user_id' => $someoneElse->id,
            'trip_date' => '2026-09-28',
            'legs' => [
                ['leg' => 'morning', 'status' => 'taken'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('ferry_trips', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('ferry_trips', ['user_id' => $someoneElse->id]);
    }

    public function test_the_same_leg_cannot_be_sent_twice_in_one_request(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '2026-09-28',
            'legs' => [
                ['leg' => 'morning', 'status' => 'taken'],
                ['leg' => 'morning', 'status' => 'missed'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('legs.0.leg');

        $this->assertDatabaseCount('ferry_trips', 0);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '2026-09-28',
            'legs' => [
                ['leg' => 'morning', 'status' => 'sailed-away'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('legs.0.status');
    }

    public function test_the_trip_date_must_be_iso_formatted(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/ferry-trips/day', [
            'trip_date' => '28-09-2026',
            'legs' => [
                ['leg' => 'morning', 'status' => 'taken'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('trip_date');
    }

    public function test_index_groups_each_date_into_its_legs(): void
    {
        $user = $this->actingAsUser();

        FerryTrip::factory()->morning()->on('2026-09-27')->create(['user_id' => $user->id]);
        FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $user->id]);
        FerryTrip::factory()->evening()->on('2026-09-28')->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/ferry-trips?month=2026-09')->assertOk();

        $response->assertJsonPath('month', '2026-09')
            ->assertJsonCount(2, 'days')
            ->assertJsonPath('days.0.date', '2026-09-27')
            ->assertJsonPath('days.0.morning.leg', 'morning')
            ->assertJsonPath('days.0.evening', null)
            ->assertJsonPath('days.1.date', '2026-09-28')
            ->assertJsonPath('days.1.evening.leg', 'evening');

        $this->assertEquals(0.5, $response->json('days.0.day_count'));
        $this->assertEquals(1.0, $response->json('days.1.day_count'));
    }

    public function test_index_hides_the_user_id_of_each_leg(): void
    {
        $user = $this->actingAsUser();
        FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $user->id]);

        $leg = $this->getJson('/api/ferry-trips?month=2026-09')->assertOk()->json('days.0.morning');

        $this->assertArrayNotHasKey('user_id', $leg);
        $this->assertEquals(0.5, $leg['count']);
    }

    public function test_index_only_returns_the_requested_month(): void
    {
        $user = $this->actingAsUser();

        FerryTrip::factory()->morning()->on('2026-09-30')->create(['user_id' => $user->id]);
        FerryTrip::factory()->morning()->on('2026-10-01')->create(['user_id' => $user->id]);
        FerryTrip::factory()->morning()->on('2026-08-31')->create(['user_id' => $user->id]);

        $this->getJson('/api/ferry-trips?month=2026-09')
            ->assertOk()
            ->assertJsonCount(1, 'days')
            ->assertJsonPath('days.0.date', '2026-09-30');
    }

    public function test_index_only_returns_the_users_own_trips(): void
    {
        $user = $this->actingAsUser();
        $someoneElse = User::factory()->create();

        FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $user->id]);
        FerryTrip::factory()->evening()->on('2026-09-28')->create(['user_id' => $someoneElse->id]);

        $response = $this->getJson('/api/ferry-trips?month=2026-09')->assertOk();

        $response->assertJsonCount(1, 'days')->assertJsonPath('days.0.evening', null);
        $this->assertEquals(0.5, $response->json('days.0.day_count'));
    }

    public function test_summary_adds_full_and_half_days_together(): void
    {
        $user = $this->actingAsUser();

        FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $user->id]);
        FerryTrip::factory()->evening()->on('2026-09-28')->create(['user_id' => $user->id]);
        FerryTrip::factory()->morning()->on('2026-09-27')->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/ferry-trips/summary?month=2026-09')->assertOk();

        $response->assertJsonPath('month', '2026-09')
            ->assertJsonPath('full_days', 1)
            ->assertJsonPath('half_days', 1);

        $this->assertEquals(1.5, $response->json('total_days'));
    }

    public function test_summary_ignores_legs_that_were_not_taken(): void
    {
        $user = $this->actingAsUser();

        FerryTrip::factory()->morning()->missed()->on('2026-09-28')->create(['user_id' => $user->id]);
        FerryTrip::factory()->evening()->cancelled()->on('2026-09-28')->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/ferry-trips/summary?month=2026-09')->assertOk();

        $response->assertJsonPath('full_days', 0)->assertJsonPath('half_days', 0);
        $this->assertEquals(0.0, $response->json('total_days'));
    }

    public function test_summary_returns_zeroes_for_a_month_with_no_trips(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/ferry-trips/summary?month=2026-09')->assertOk();

        $response->assertJsonPath('full_days', 0)->assertJsonPath('half_days', 0);
        $this->assertEquals(0.0, $response->json('total_days'));
    }

    public function test_summary_defaults_to_the_current_month(): void
    {
        $user = $this->actingAsUser();
        FerryTrip::factory()->morning()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/ferry-trips/summary')->assertOk();

        $response->assertJsonPath('month', now()->format('Y-m'));
        $this->assertEquals(0.5, $response->json('total_days'));
    }

    public function test_a_short_month_resolves_correctly_at_the_end_of_a_long_one(): void
    {
        // Regression: Carbon::createFromFormat('Y-m', ...) without a leading "!"
        // inherits today's day-of-month, so "2026-02" would overflow into March.
        $this->travelTo('2026-01-31');

        $user = $this->actingAsUser();
        FerryTrip::factory()->morning()->on('2026-02-10')->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/ferry-trips/summary?month=2026-02')->assertOk();

        $response->assertJsonPath('month', '2026-02');
        $this->assertEquals(0.5, $response->json('total_days'));

        $this->getJson('/api/ferry-trips?month=2026-02')
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-02-10');
    }

    public function test_the_month_parameter_must_be_year_dash_month(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/ferry-trips?month=2026-9')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson('/api/ferry-trips/summary?month=September')->assertUnprocessable()->assertJsonValidationErrors('month');
    }

    public function test_a_leg_can_be_deleted(): void
    {
        $user = $this->actingAsUser();
        $trip = FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $user->id]);

        $this->deleteJson("/api/ferry-trips/{$trip->id}")->assertNoContent();

        $this->assertDatabaseMissing('ferry_trips', ['id' => $trip->id]);
    }

    public function test_another_users_leg_is_hidden_behind_a_404(): void
    {
        $this->actingAsUser();
        $someoneElse = User::factory()->create();
        $trip = FerryTrip::factory()->morning()->on('2026-09-28')->create(['user_id' => $someoneElse->id]);

        $this->deleteJson("/api/ferry-trips/{$trip->id}")->assertNotFound();

        $this->assertDatabaseHas('ferry_trips', ['id' => $trip->id]);
    }

    public function test_deleting_a_missing_leg_returns_a_404(): void
    {
        $this->actingAsUser();

        $this->deleteJson('/api/ferry-trips/999')->assertNotFound();
    }

    public function test_every_ferry_route_requires_authentication(): void
    {
        $this->getJson('/api/ferry-trips')->assertUnauthorized();
        $this->getJson('/api/ferry-trips/summary')->assertUnauthorized();
        $this->postJson('/api/ferry-trips/day', [])->assertUnauthorized();
        $this->deleteJson('/api/ferry-trips/1')->assertUnauthorized();
    }
}
