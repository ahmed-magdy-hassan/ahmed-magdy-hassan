<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Contracts\TwoFactorProviderInterface;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\ValueObjects\Auth\TwoFactorCredentials;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\BcryptHasher;
use RuntimeException;
use Tests\TestCase;

final class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorService $service;
    private TwoFactorProviderInterface $provider;
    private BcryptHasher $hasher;
    private Encrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider  = $this->createMock(TwoFactorProviderInterface::class);
        $this->hasher    = new BcryptHasher(['rounds' => 4]); // low rounds for speed
        $this->encrypter = $this->app->make(Encrypter::class);

        $this->service = new TwoFactorService(
            provider:  $this->provider,
            hasher:    $this->hasher,
            encrypter: $this->encrypter,
        );
    }

    // -----------------------------------------------------------------------
    // enable()
    // -----------------------------------------------------------------------

    /** @test */
    public function enable_returns_credentials_with_secret_qr_and_eight_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->provider->method('generateSecretKey')->willReturn('FAKESECRET32CHARS0000000000000AA');
        $this->provider->method('getQRCodeUrl')->willReturn('otpauth://totp/HRIST:u@e.com?secret=FAKE');

        $credentials = $this->service->enable($user);

        $this->assertInstanceOf(TwoFactorCredentials::class, $credentials);
        $this->assertSame('FAKESECRET32CHARS0000000000000AA', $credentials->secret);
        $this->assertStringStartsWith('otpauth://', $credentials->qrCodeUrl);
        $this->assertCount(8, $credentials->recoveryCodes);
    }

    /** @test */
    public function enable_stores_encrypted_secret_on_user(): void
    {
        $user = User::factory()->create();
        $this->provider->method('generateSecretKey')->willReturn('MYSECRET');
        $this->provider->method('getQRCodeUrl')->willReturn('otpauth://totp/test');

        $this->service->enable($user);

        $stored = $user->fresh()->two_factor_secret;
        $this->assertNotNull($stored);
        $this->assertSame('MYSECRET', $this->encrypter->decrypt($stored));
    }

    /** @test */
    public function enable_stores_hashed_recovery_codes_not_plain_text(): void
    {
        $user = User::factory()->create();
        $this->provider->method('generateSecretKey')->willReturn('S');
        $this->provider->method('getQRCodeUrl')->willReturn('otpauth://totp/test');

        $credentials = $this->service->enable($user);

        $storedJson  = $this->encrypter->decrypt($user->fresh()->two_factor_recovery_codes);
        $storedCodes = json_decode($storedJson, true);

        $this->assertCount(8, $storedCodes);

        // Stored values must be bcrypt hashes, not the plain-text codes
        foreach ($credentials->recoveryCodes as $i => $plain) {
            $this->assertStringStartsWith('$2', $storedCodes[$i]);
        }
    }

    /** @test */
    public function enable_does_not_set_two_factor_confirmed_at(): void
    {
        $user = User::factory()->create();
        $this->provider->method('generateSecretKey')->willReturn('S');
        $this->provider->method('getQRCodeUrl')->willReturn('otpauth://totp/test');

        $this->service->enable($user);

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    /** @test */
    public function enable_throws_if_two_factor_already_confirmed(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Two-factor authentication is already active.');

        $this->service->enable($user);
    }

    // -----------------------------------------------------------------------
    // confirm()
    // -----------------------------------------------------------------------

    /** @test */
    public function confirm_returns_false_when_secret_is_null(): void
    {
        $user = User::factory()->create(['two_factor_secret' => null]);

        $this->assertFalse($this->service->confirm($user, '123456'));
    }

    /** @test */
    public function confirm_returns_false_on_invalid_totp_code(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => $this->encrypter->encrypt('FAKESECRET'),
        ]);
        $this->provider->method('verifyCode')->willReturn(false);

        $this->assertFalse($this->service->confirm($user, '000000'));
    }

    /** @test */
    public function confirm_sets_confirmed_at_on_valid_code(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => $this->encrypter->encrypt('FAKESECRET'),
        ]);
        $this->provider->method('verifyCode')->willReturn(true);

        $this->assertTrue($this->service->confirm($user, '123456'));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    // -----------------------------------------------------------------------
    // disable()
    // -----------------------------------------------------------------------

    /** @test */
    public function disable_clears_all_two_factor_fields(): void
    {
        $user = User::factory()->create([
            'two_factor_secret'         => $this->encrypter->encrypt('S'),
            'two_factor_recovery_codes' => $this->encrypter->encrypt('[]'),
            'two_factor_confirmed_at'   => now(),
        ]);

        $this->service->disable($user);

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    // -----------------------------------------------------------------------
    // verify()
    // -----------------------------------------------------------------------

    /** @test */
    public function verify_returns_false_when_two_factor_not_confirmed(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => null]);

        $this->assertFalse($this->service->verify($user, '123456'));
    }

    /** @test */
    public function verify_delegates_to_provider_with_decrypted_secret(): void
    {
        $user = User::factory()->create([
            'two_factor_secret'       => $this->encrypter->encrypt('VERIFYSECRET'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->provider
            ->expects($this->once())
            ->method('verifyCode')
            ->with('VERIFYSECRET', '654321')
            ->willReturn(true);

        $this->assertTrue($this->service->verify($user, '654321'));
    }

    // -----------------------------------------------------------------------
    // verifyRecoveryCode()
    // -----------------------------------------------------------------------

    /** @test */
    public function verify_recovery_code_returns_false_when_no_codes_stored(): void
    {
        $user = User::factory()->create(['two_factor_recovery_codes' => null]);

        $this->assertFalse($this->service->verifyRecoveryCode($user, 'abcd1234-efgh5678'));
    }

    /** @test */
    public function verify_recovery_code_accepts_matching_code_and_removes_it(): void
    {
        $plain      = 'abcd1234-efgh5678';
        $normalised = 'abcd1234efgh5678';
        $codes      = [
            $this->hasher->make($normalised),
            $this->hasher->make('other0000other0000'),
        ];

        $user = User::factory()->create([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(json_encode($codes)),
        ]);

        $this->assertTrue($this->service->verifyRecoveryCode($user, $plain));

        $remaining = json_decode(
            $this->encrypter->decrypt($user->fresh()->two_factor_recovery_codes),
            true,
        );
        $this->assertCount(1, $remaining);
    }

    /** @test */
    public function verify_recovery_code_is_case_insensitive_and_strips_dashes(): void
    {
        $plain      = 'ABCD1234-EFGH5678';
        $normalised = 'abcd1234efgh5678';

        $user = User::factory()->create([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(
                json_encode([$this->hasher->make($normalised)])
            ),
        ]);

        $this->assertTrue($this->service->verifyRecoveryCode($user, $plain));
    }

    /** @test */
    public function verify_recovery_code_returns_false_for_wrong_code(): void
    {
        $user = User::factory()->create([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(
                json_encode([$this->hasher->make('abcd1234efgh5678')])
            ),
        ]);

        $this->assertFalse($this->service->verifyRecoveryCode($user, 'completely-wrong'));
    }

    // -----------------------------------------------------------------------
    // regenerateRecoveryCodes()
    // -----------------------------------------------------------------------

    /** @test */
    public function regenerate_recovery_codes_returns_eight_new_plain_text_codes(): void
    {
        $user  = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $codes = $this->service->regenerateRecoveryCodes($user);

        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{8}$/', $code);
        }
    }

    /** @test */
    public function regenerate_recovery_codes_replaces_existing_codes_in_storage(): void
    {
        $oldCodes = [$this->hasher->make('oldcode')];
        $user     = User::factory()->create([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(json_encode($oldCodes)),
        ]);

        $newCodes = $this->service->regenerateRecoveryCodes($user);

        $storedHashes = json_decode(
            $this->encrypter->decrypt($user->fresh()->two_factor_recovery_codes),
            true,
        );

        // Old codes are gone and new codes are hashed in storage
        $this->assertCount(8, $storedHashes);
        $this->assertTrue($this->hasher->check(
            strtolower(str_replace('-', '', $newCodes[0])),
            $storedHashes[0],
        ));
    }

    // -----------------------------------------------------------------------
    // remainingRecoveryCodes()
    // -----------------------------------------------------------------------

    /** @test */
    public function remaining_recovery_codes_returns_null_when_not_set(): void
    {
        $user = User::factory()->create(['two_factor_recovery_codes' => null]);

        $this->assertNull($this->service->remainingRecoveryCodes($user));
    }

    /** @test */
    public function remaining_recovery_codes_returns_count_of_stored_codes(): void
    {
        $codes = array_map(fn($i) => $this->hasher->make("code-$i"), range(1, 5));

        $user = User::factory()->create([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(json_encode($codes)),
        ]);

        $this->assertSame(5, $this->service->remainingRecoveryCodes($user));
    }
}
