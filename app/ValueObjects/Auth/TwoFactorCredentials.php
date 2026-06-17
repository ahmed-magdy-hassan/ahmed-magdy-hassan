<?php

declare(strict_types=1);

namespace App\ValueObjects\Auth;

/**
 * Immutable result of a successful 2FA enrolment.
 *
 * The secret and recovery codes are shown ONCE to the user at enrolment time.
 * After the first display, only the hashed recovery codes remain in the database.
 */
final readonly class TwoFactorCredentials
{
    /**
     * @param string   $secret        Plain-text base32 TOTP secret (for QR code display)
     * @param string   $qrCodeUrl     otpauth:// URI scannable by authenticator apps
     * @param string[] $recoveryCodes Plain-text one-time recovery codes (8 codes)
     */
    public function __construct(
        public readonly string $secret,
        public readonly string $qrCodeUrl,
        public readonly array  $recoveryCodes,
    ) {}

    public function toArray(): array
    {
        return [
            'secret'         => $this->secret,
            'qr_code_url'    => $this->qrCodeUrl,
            'recovery_codes' => $this->recoveryCodes,
        ];
    }
}
