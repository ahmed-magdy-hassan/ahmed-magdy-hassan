<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\TwoFactorProviderInterface;
use App\Models\User;
use App\ValueObjects\Auth\TwoFactorCredentials;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Hashing\Hasher;
use RuntimeException;

/**
 * Orchestrates TOTP-based two-factor authentication lifecycle.
 *
 * Secrets and recovery-code hashes are stored encrypted (AES-256) in the database.
 * Recovery codes are bcrypt-hashed before storage so a DB breach cannot reveal them.
 *
 * Depends on TwoFactorProviderInterface and injectable Hasher/Encrypter so the
 * entire service is unit-testable without the full Laravel application container.
 */
final class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly TwoFactorProviderInterface $provider,
        private readonly Hasher                     $hasher,
        private readonly Encrypter                  $encrypter,
    ) {}

    /**
     * Initiate 2FA enrolment for a user: generate a TOTP secret and recovery codes.
     * The user must call confirm() with a valid TOTP code before 2FA is active.
     *
     * @throws RuntimeException if 2FA is already confirmed for this user.
     */
    public function enable(User $user): TwoFactorCredentials
    {
        if ($user->hasConfirmedTwoFactor()) {
            throw new RuntimeException('Two-factor authentication is already active.');
        }

        $secret        = $this->provider->generateSecretKey();
        $recoveryCodes = $this->freshRecoveryCodes();
        $qrCodeUrl     = $this->provider->getQRCodeUrl(
            config('app.name', 'HRIST'),
            $user->email,
            $secret,
        );

        $user->forceFill([
            'two_factor_secret'         => $this->encrypter->encrypt($secret),
            'two_factor_recovery_codes' => $this->encrypter->encrypt(
                json_encode($this->hashAll($recoveryCodes), JSON_THROW_ON_ERROR)
            ),
            'two_factor_confirmed_at'   => null,
        ])->save();

        return new TwoFactorCredentials(
            secret: $secret,
            qrCodeUrl: $qrCodeUrl,
            recoveryCodes: $recoveryCodes,
        );
    }

    /**
     * Confirm a pending 2FA enrolment by verifying the first TOTP code.
     * Returns true and marks the user as confirmed; false on wrong code.
     */
    public function confirm(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $secret = $this->encrypter->decrypt($user->two_factor_secret);

        if (!$this->provider->verifyCode($secret, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /**
     * Disable 2FA entirely for a user, wiping all 2FA fields.
     * Callers are responsible for verifying the current TOTP code before calling this.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();
    }

    /**
     * Verify a TOTP code against the user's confirmed secret.
     * Returns false if 2FA is not confirmed or the code is invalid.
     */
    public function verify(User $user, string $code): bool
    {
        if (!$user->hasConfirmedTwoFactor()) {
            return false;
        }

        $secret = $this->encrypter->decrypt($user->two_factor_secret);

        return $this->provider->verifyCode($secret, $code);
    }

    /**
     * Verify a one-time recovery code.
     * Consumed codes are removed from storage; returns false if the code is invalid.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        $storedHashes = json_decode(
            $this->encrypter->decrypt($user->two_factor_recovery_codes),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $normalised = strtolower(str_replace([' ', '-'], '', $code));

        foreach ($storedHashes as $index => $hash) {
            if ($this->hasher->check($normalised, $hash)) {
                unset($storedHashes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => $this->encrypter->encrypt(
                        json_encode(array_values($storedHashes), JSON_THROW_ON_ERROR)
                    ),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Generate a fresh set of recovery codes.
     * Returns the plain-text codes (shown once); hashed versions are stored.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->freshRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->encrypter->encrypt(
                json_encode($this->hashAll($recoveryCodes), JSON_THROW_ON_ERROR)
            ),
        ])->save();

        return $recoveryCodes;
    }

    /**
     * How many recovery codes remain unused for a confirmed user.
     * Returns null if 2FA is not enabled.
     */
    public function remainingRecoveryCodes(User $user): ?int
    {
        if ($user->two_factor_recovery_codes === null) {
            return null;
        }

        $codes = json_decode(
            $this->encrypter->decrypt($user->two_factor_recovery_codes),
            true,
        );

        return count($codes);
    }

    // -----------------------------------------------------------------------

    private function freshRecoveryCodes(): array
    {
        return array_map(
            fn() => sprintf('%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(4))),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    /** Hash all codes for storage. Input codes are normalised before hashing. */
    private function hashAll(array $codes): array
    {
        return array_map(
            fn(string $code) => $this->hasher->make(
                strtolower(str_replace('-', '', $code))
            ),
            $codes,
        );
    }
}
