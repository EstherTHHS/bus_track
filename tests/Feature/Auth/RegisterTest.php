<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_screen_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/Register'));
    }

    public function test_registering_creates_the_user_and_signs_them_in(): void
    {
        $this->post('/register', [
            'name' => 'Esther',
            'email' => 'esther@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'esther@test.com']);
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Esther',
                'email' => 'esther@test.com',
                'password' => 'password123',
                'password_confirmation' => 'something-else',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'esther@test.com']);

        $this->from('/register')
            ->post('/register', [
                'name' => 'Esther',
                'email' => 'esther@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }
}
