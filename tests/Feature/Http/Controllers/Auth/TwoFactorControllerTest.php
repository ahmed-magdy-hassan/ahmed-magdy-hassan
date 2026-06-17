<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\ValueObjects\Auth\TwoFactorCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class TwoFactorControllerTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // POST /api/auth/two-factor — store()
    // -----------------------------------------------------------------------

    /** @test */
    public function store_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/auth/two-factor')
            ->assertUnauthorized();
    }

    /** @test */
    public function store_returns_201_with_credential_structure_on_success(): void
    {
        $user        = User::factory()->create();
        $credentials = new TwoFactorCredentials(
            secret: 'FAKESECRETKEY32C',
            qrCodeUrl: 'otpauth://totp/HRIST:user@example.com?secret=FAKESECRETKEY32C',
            recoveryCodes: array_fill(0, 8, 'abcd1234-efgh5678'),
        );

        $this->mock(TwoFactorService::class)
            ->shouldReceive('enable')
            ->once()
            ->andReturn($credentials);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor')
            ->assertCreated()
            ->assertJsonStructure(['secret', 'qr_code_url', 'recovery_codes'])
            ->assertJsonCount(8, 'recovery_codes');
    }

    /** @test */
    public function store_returns_422_when_two_factor_already_active(): void
    {
        $user = User::factory()->create();

        $this->mock(TwoFactorService::class)
            ->shouldReceive('enable')
            ->andThrow(new RuntimeException('Two-factor authentication is already active.'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor')
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Two-factor authentication is already active.']);
    }

    // -----------------------------------------------------------------------
    // POST /api/auth/two-factor/confirm — confirm()
    // -----------------------------------------------------------------------

    /** @test */
    public function confirm_returns_200_when_totp_code_is_valid(): void
    {
        $user = User::factory()->create();

        $this->mock(TwoFactorService::class)
            ->shouldReceive('confirm')
            ->andReturn(true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', ['code' => '123456'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Two-factor authentication has been enabled.']);
    }

    /** @test */
    public function confirm_returns_422_when_totp_code_is_invalid(): void
    {
        $user = User::factory()->create();

        $this->mock(TwoFactorService::class)
            ->shouldReceive('confirm')
            ->andReturn(false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'The provided code is invalid.']);
    }

    /** @test */
    public function confirm_returns_422_when_code_field_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('code');
    }

    /** @test */
    public function confirm_returns_422_when_code_is_not_six_digits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', ['code' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('code');
    }

    // -----------------------------------------------------------------------
    // DELETE /api/auth/two-factor — destroy()
    // -----------------------------------------------------------------------

    /** @test */
    public function destroy_disables_two_factor_on_valid_code(): void
    {
        $user = User::factory()->create();
        $mock = $this->mock(TwoFactorService::class);
        $mock->shouldReceive('verify')->andReturn(true);
        $mock->shouldReceive('disable')->once();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/two-factor', ['code' => '123456'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Two-factor authentication has been disabled.']);
    }

    /** @test */
    public function destroy_returns_422_when_totp_code_is_invalid(): void
    {
        $user = User::factory()->create();

        $this->mock(TwoFactorService::class)
            ->shouldReceive('verify')
            ->andReturn(false);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/two-factor', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'The provided code is invalid.']);
    }

    // -----------------------------------------------------------------------
    // GET /api/auth/two-factor/recovery-codes — recoveryCodes()
    // -----------------------------------------------------------------------

    /** @test */
    public function recovery_codes_returns_remaining_count_for_confirmed_user(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->mock(TwoFactorService::class)
            ->shouldReceive('remainingRecoveryCodes')
            ->andReturn(6);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/two-factor/recovery-codes')
            ->assertOk()
            ->assertExactJson(['remaining' => 6]);
    }

    /** @test */
    public function recovery_codes_returns_422_when_two_factor_not_enabled(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/two-factor/recovery-codes')
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Two-factor authentication is not enabled.']);
    }

    // -----------------------------------------------------------------------
    // POST /api/auth/two-factor/recovery-codes — regenerateRecoveryCodes()
    // -----------------------------------------------------------------------

    /** @test */
    public function regenerate_returns_201_with_new_codes_on_valid_totp(): void
    {
        $user  = User::factory()->create();
        $codes = array_fill(0, 8, 'abcd1234-efgh5678');

        $mock = $this->mock(TwoFactorService::class);
        $mock->shouldReceive('verify')->andReturn(true);
        $mock->shouldReceive('regenerateRecoveryCodes')->andReturn($codes);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/recovery-codes', ['code' => '123456'])
            ->assertStatus(201)
            ->assertJsonStructure(['recovery_codes'])
            ->assertJsonCount(8, 'recovery_codes');
    }

    /** @test */
    public function regenerate_returns_422_when_totp_code_is_invalid(): void
    {
        $user = User::factory()->create();

        $this->mock(TwoFactorService::class)
            ->shouldReceive('verify')
            ->andReturn(false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/recovery-codes', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'The provided code is invalid.']);
    }
}
