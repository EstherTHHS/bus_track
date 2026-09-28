<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_the_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Esther',
            'email' => 'esther@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonPath('user.email', 'esther@test.com');

        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user = User::firstWhere('email', 'esther@test.com');
        $this->assertTrue(Hash::check('password123', $user->password), 'The password should be stored hashed.');
    }

    public function test_register_requires_a_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Esther',
            'email' => 'esther@test.com',
            'password' => 'password123',
            'password_confirmation' => 'something-else',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_requires_a_password_of_at_least_eight_characters(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Esther',
            'email' => 'esther@test.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_register_rejects_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'esther@test.com']);

        $this->postJson('/api/register', [
            'name' => 'Esther',
            'email' => 'esther@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'esther@test.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'esther@test.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'esther@test.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'esther@test.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_an_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@test.com',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('react-app')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_token_used_for_the_request(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device')->plainTextToken;
        $user->createToken('other-device');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeader('Authorization', "Bearer {$current}")
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'other-device']);
    }

    public function test_a_revoked_token_is_rejected(): void
    {
        // Asserted in its own test: within a single test the auth guard caches
        // the user it already resolved, so a second request would still pass.
        $user = User::factory()->create();
        $token = $user->createToken('revoked-device')->plainTextToken;
        $user->tokens()->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_requires_a_token(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
