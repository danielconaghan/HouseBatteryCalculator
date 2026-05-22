<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_422_with_missing_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable();
    }

    public function test_login_returns_422_with_invalid_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'secret',
        ])->assertUnprocessable();
    }

    public function test_login_returns_401_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'daniel@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'daniel@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_returns_401_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'secret',
        ])->assertUnauthorized();
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'daniel@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'daniel@example.com',
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_logout_deletes_token_from_database(): void
    {
        $user        = User::factory()->create();
        $tokenResult = $user->createToken('ui');

        $this->withToken($tokenResult->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenResult->accessToken->id,
        ]);
    }
}
