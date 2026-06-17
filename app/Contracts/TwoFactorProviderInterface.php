<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstracts the TOTP algorithm so the concrete implementation
 * (Google Authenticator protocol, RFC 6238) can be swapped in tests.
 */
interface TwoFactorProviderInterface
{
    /**
     * Generate a new cryptographically-random TOTP secret.
     * Returns a base32-encoded string safe to share as a QR code payload.
     */
    public function generateSecretKey(): string;

    /**
     * Return an otpauth:// URI for the given account.
     * Scannable directly by Google Authenticator, Authy, and compatible apps.
     *
     * @param string $issuer  Human-readable app name shown in the authenticator (e.g. "HRIST")
     * @param string $email   The user's account label shown beneath the issuer
     * @param string $secret  The base32-encoded secret returned by generateSecretKey()
     */
    public function getQRCodeUrl(string $issuer, string $email, string $secret): string;

    /**
     * Verify a 6-digit TOTP code against the stored secret.
     * Implementations MUST check a ±1 time-step window for clock drift tolerance
     * and use constant-time comparison to prevent timing attacks.
     */
    public function verifyCode(string $secret, string $code): bool;
}
