<?php

namespace Tests\Feature;

use App\Models\FerryTrip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FerryDayWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_both_legs_of_a_day(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [
                    ['leg' => 'morning', 'trip_time' => '07:45', 'status' => 'taken'],
                    ['leg' => 'evening', 'trip_time' => '18:10', 'status' => 'taken'],
                ],
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ferry_trips', 2);
        $this->assertDatabaseHas('ferry_trips', [
            'user_id' => $user->id,
            'trip_date' => '2026-09-28',
            'leg' => 'morning',
            'status' => 'taken',
        ]);
    }

    public function test_reposting_a_leg_corrects_it_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        FerryTrip::factory()->for($user)->on('2026-09-28')->morning()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [
                    ['leg' => 'morning', 'status' => 'missed', 'note' => 'Overslept'],
                ],
            ])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseCount('ferry_trips', 1);
        $this->assertDatabaseHas('ferry_trips', [
            'trip_date' => '2026-09-28',
            'leg' => 'morning',
            'status' => 'missed',
            'note' => 'Overslept',
            'trip_time' => null,
        ]);
    }

    public function test_it_rejects_an_invalid_status(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [['leg' => 'morning', 'status' => 'sailed-away']],
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('legs.0.status');

        $this->assertDatabaseCount('ferry_trips', 0);
    }

    public function test_it_deletes_a_leg(): void
    {
        $user = User::factory()->create();
        $trip = FerryTrip::factory()->for($user)->on('2026-09-28')->morning()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->delete("/ferry-trips/{$trip->id}")
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ferry_trips', 0);
    }

    public function test_it_cannot_delete_another_users_leg(): void
    {
        $trip = FerryTrip::factory()->for(User::factory())->on('2026-09-28')->morning()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/ferry-trips/{$trip->id}")
            ->assertNotFound();

        $this->assertDatabaseCount('ferry_trips', 1);
    }

    public function test_it_updates_the_identified_row_when_an_id_is_given(): void
    {
        $user = User::factory()->create();
        $trip = FerryTrip::factory()->for($user)->on('2026-09-28')->morning()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [
                    [
                        'id' => $trip->id,
                        'leg' => 'morning',
                        'status' => 'cancelled',
                        'trip_time' => '08:15',
                        'note' => 'Fog',
                    ],
                ],
            ])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseCount('ferry_trips', 1);
        $this->assertDatabaseHas('ferry_trips', [
            'id' => $trip->id,
            'status' => 'cancelled',
            'note' => 'Fog',
        ]);
    }

    public function test_an_id_belonging_to_another_user_is_rejected(): void
    {
        $theirTrip = FerryTrip::factory()->for(User::factory())->on('2026-09-28')->morning()->create();

        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [['id' => $theirTrip->id, 'leg' => 'morning', 'status' => 'missed']],
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('legs.0.id');

        $this->assertDatabaseHas('ferry_trips', [
            'id' => $theirTrip->id,
            'status' => 'taken',
        ]);
    }

    public function test_an_id_from_another_date_falls_back_to_the_unique_index(): void
    {
        $user = User::factory()->create();
        // The caller's own leg, but not the one being written.
        $elsewhere = FerryTrip::factory()->for($user)->on('2026-09-01')->morning()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/ferry-trips/day', [
                'trip_date' => '2026-09-28',
                'legs' => [['id' => $elsewhere->id, 'leg' => 'morning', 'status' => 'taken']],
            ])
            ->assertRedirect('/dashboard');

        // Created rather than dragged off its own date.
        $this->assertDatabaseCount('ferry_trips', 2);
        $this->assertDatabaseHas('ferry_trips', [
            'id' => $elsewhere->id,
            'trip_date' => '2026-09-01',
        ]);
        $this->assertDatabaseHas('ferry_trips', [
            'trip_date' => '2026-09-28',
            'leg' => 'morning',
        ]);
    }

    public function test_guests_cannot_write(): void
    {
        $this->post('/ferry-trips/day', [])->assertRedirect('/login');
        $this->delete('/ferry-trips/1')->assertRedirect('/login');
    }
}
