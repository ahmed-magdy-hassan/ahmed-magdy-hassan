<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\TwoFactorProviderInterface;

/**
 * Pure-PHP implementation of RFC 6238 TOTP (Google Authenticator protocol).
 *
 * No external package dependencies — uses only PHP's built-in hash_hmac and
 * random_bytes, making it deployable in any environment.
 *
 * Compatible with: Google Authenticator, Authy, Microsoft Authenticator,
 * 1Password, Bitwarden, and any RFC 6238-compliant TOTP app.
 */
final class GoogleTwoFactorProvider implements TwoFactorProviderInterface
{
    private const BASE32_CHARS  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP_SECONDS  = 30;   // RFC 6238 default time step
    private const WINDOW        = 1;    // Check ±1 step to tolerate clock drift
    private const CODE_DIGITS   = 6;
    private const SECRET_BYTES  = 20;   // 160-bit key = 32 base32 chars

    public function generateSecretKey(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public function getQRCodeUrl(string $issuer, string $email, string $secret): string
    {
        $label  = rawurlencode($issuer) . ':' . rawurlencode($email);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::CODE_DIGITS,
            'period'    => self::STEP_SECONDS,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    public function verifyCode(string $secret, string $code): bool
    {
        if (!ctype_digit($code) || strlen($code) !== self::CODE_DIGITS) {
            return false;
        }

        $keyBytes = $this->base32Decode($secret);
        $counter  = (int) floor(time() / self::STEP_SECONDS);

        for ($delta = -self::WINDOW; $delta <= self::WINDOW; $delta++) {
            // hash_equals provides constant-time comparison (TOTP is not secret, but good practice)
            if (hash_equals($this->generateCode($keyBytes, $counter + $delta), $code)) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------

    /**
     * Compute a HOTP value for the given 8-byte counter (RFC 4226).
     * TOTP is HOTP with counter = floor(unix_time / step).
     */
    private function generateCode(string $keyBytes, int $counter): string
    {
        // Pack counter as 8-byte big-endian (high word first)
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hmac         = hash_hmac('sha1', $counterBytes, $keyBytes, binary: true);

        // Dynamic truncation (RFC 4226 §5.4)
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0f;
        $code   = (
            ((ord($hmac[$offset])     & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) <<  8) |
            ((ord($hmac[$offset + 3]) & 0xff))
        ) % (10 ** self::CODE_DIGITS);

        return str_pad((string) $code, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $chars  = self::BASE32_CHARS;
        $output = '';
        $bits   = 0;
        $buffer = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits  += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $output .= $chars[($buffer >> $bits) & 0x1f];
            }
        }

        if ($bits > 0) {
            $output .= $chars[($buffer << (5 - $bits)) & 0x1f];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $map     = array_flip(str_split(self::BASE32_CHARS));
        $encoded = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $encoded));
        $output  = '';
        $bits    = 0;
        $buffer  = 0;

        foreach (str_split($encoded) as $char) {
            if (!isset($map[$char])) {
                continue;
            }
            $buffer = ($buffer << 5) | $map[$char];
            $bits  += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
            }
        }

        return $output;
    }
}
