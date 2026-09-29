<?php

namespace Tests\Feature;

use App\Models\FerryTrip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_root_url_sends_you_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_it_renders_the_month_grouped_into_days_with_its_summary(): void
    {
        $user = User::factory()->create();
        FerryTrip::factory()->for($user)->on('2026-09-27')->morning()->create();
        FerryTrip::factory()->for($user)->on('2026-09-28')->morning()->create();
        FerryTrip::factory()->for($user)->on('2026-09-28')->evening()->create();

        $this->actingAs($user)
            ->get('/dashboard?month=2026-09')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('month', '2026-09')
                ->has('days', 2)
                ->where('days.0.date', '2026-09-27')
                ->where('days.0.day_count', 0.5)
                ->where('days.0.evening', null)
                ->where('days.1.date', '2026-09-28')
                // Whole floats lose their decimal in JSON: 1.0 arrives as 1.
                ->where('days.1.day_count', 1)
                ->where('summary.total_days', 1.5)
                ->where('summary.full_days', 1)
                ->where('summary.half_days', 1)
            );
    }

    public function test_it_hides_other_users_trips(): void
    {
        $user = User::factory()->create();
        FerryTrip::factory()->for(User::factory())->on('2026-09-28')->morning()->create();

        $this->actingAs($user)
            ->get('/dashboard?month=2026-09')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('days', 0)
                ->where('summary.total_days', 0)
            );
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('month', now()->format('Y-m'))
            );
    }

    public function test_it_rejects_a_badly_formatted_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->get('/dashboard?month=2026-9')
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('month');
    }

    public function test_it_shares_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->missing('auth.user.password')
            );
    }
}
