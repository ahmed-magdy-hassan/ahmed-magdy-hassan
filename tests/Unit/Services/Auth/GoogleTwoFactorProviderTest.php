<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\GoogleTwoFactorProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GoogleTwoFactorProviderTest extends TestCase
{
    private GoogleTwoFactorProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new GoogleTwoFactorProvider();
    }

    // -----------------------------------------------------------------------
    // generateSecretKey()
    // -----------------------------------------------------------------------

    /** @test */
    public function generate_secret_key_returns_base32_encoded_string(): void
    {
        $secret = $this->provider->generateSecretKey();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    /** @test */
    public function generate_secret_key_returns_32_characters_for_160_bit_secret(): void
    {
        // 20 bytes × 8 bits / 5 bits-per-char = 32 base32 chars
        $this->assertSame(32, strlen($this->provider->generateSecretKey()));
    }

    /** @test */
    public function generate_secret_key_produces_unique_values_on_successive_calls(): void
    {
        $a = $this->provider->generateSecretKey();
        $b = $this->provider->generateSecretKey();

        $this->assertNotSame($a, $b);
    }

    // -----------------------------------------------------------------------
    // getQRCodeUrl()
    // -----------------------------------------------------------------------

    /** @test */
    public function get_qr_code_url_returns_otpauth_totp_uri(): void
    {
        $url = $this->provider->getQRCodeUrl('HRIST', 'user@example.com', 'SECRETKEY');

        $this->assertStringStartsWith('otpauth://totp/', $url);
    }

    /** @test */
    public function get_qr_code_url_encodes_issuer_and_email_in_label(): void
    {
        $url = $this->provider->getQRCodeUrl('My App', 'user@example.com', 'SECRET');

        // rawurlencode spaces as %20 but the label uses rawurlencode for issuer
        $this->assertStringContainsString('My%20App', $url);
        $this->assertStringContainsString('user%40example.com', $url);
    }

    /** @test */
    public function get_qr_code_url_includes_secret_as_query_parameter(): void
    {
        $url = $this->provider->getQRCodeUrl('HRIST', 'user@example.com', 'MYSECRETKEY');

        $this->assertStringContainsString('secret=MYSECRETKEY', $url);
    }

    /** @test */
    public function get_qr_code_url_includes_sha1_algorithm_and_correct_digits(): void
    {
        $url = $this->provider->getQRCodeUrl('HRIST', 'user@example.com', 'KEY');

        $this->assertStringContainsString('algorithm=SHA1', $url);
        $this->assertStringContainsString('digits=6', $url);
        $this->assertStringContainsString('period=30', $url);
    }

    // -----------------------------------------------------------------------
    // verifyCode()
    // -----------------------------------------------------------------------

    /** @test */
    public function verify_code_returns_false_for_non_digit_input(): void
    {
        $secret = $this->provider->generateSecretKey();

        $this->assertFalse($this->provider->verifyCode($secret, 'abcdef'));
        $this->assertFalse($this->provider->verifyCode($secret, 'abc123'));
        $this->assertFalse($this->provider->verifyCode($secret, '12 345'));
    }

    /** @test */
    public function verify_code_returns_false_for_wrong_length(): void
    {
        $secret = $this->provider->generateSecretKey();

        $this->assertFalse($this->provider->verifyCode($secret, '12345'));   // 5 digits
        $this->assertFalse($this->provider->verifyCode($secret, '1234567')); // 7 digits
        $this->assertFalse($this->provider->verifyCode($secret, ''));
    }

    /** @test */
    public function verify_code_accepts_correct_code_for_current_time_step(): void
    {
        $secret = $this->provider->generateSecretKey();
        $code   = $this->generateCodeViaReflection($secret, (int) floor(time() / 30));

        $this->assertTrue($this->provider->verifyCode($secret, $code));
    }

    /** @test */
    public function verify_code_accepts_code_one_step_in_the_past(): void
    {
        // ±1 window tolerance for clock drift
        $secret = $this->provider->generateSecretKey();
        $code   = $this->generateCodeViaReflection($secret, (int) floor(time() / 30) - 1);

        $this->assertTrue($this->provider->verifyCode($secret, $code));
    }

    /** @test */
    public function verify_code_accepts_code_one_step_in_the_future(): void
    {
        $secret = $this->provider->generateSecretKey();
        $code   = $this->generateCodeViaReflection($secret, (int) floor(time() / 30) + 1);

        $this->assertTrue($this->provider->verifyCode($secret, $code));
    }

    /** @test */
    public function verify_code_rejects_code_two_steps_in_the_past(): void
    {
        $secret  = $this->provider->generateSecretKey();
        $code    = $this->generateCodeViaReflection($secret, (int) floor(time() / 30) - 2);
        $current = $this->generateCodeViaReflection($secret, (int) floor(time() / 30));

        // Reject IF the stale code is different from any valid window code.
        // (There's a ~1-in-1M chance the stale code collides; acceptable for tests.)
        if ($code !== $current) {
            $this->assertFalse($this->provider->verifyCode($secret, $code));
        } else {
            $this->markTestSkipped('Stale code collided with current — re-run.');
        }
    }

    // -----------------------------------------------------------------------
    // base32 encode/decode round-trip (via reflection)
    // -----------------------------------------------------------------------

    /** @test */
    public function base32_encode_decode_round_trips_arbitrary_bytes(): void
    {
        $ref    = new ReflectionClass($this->provider);
        $encode = $ref->getMethod('base32Encode');
        $decode = $ref->getMethod('base32Decode');
        $encode->setAccessible(true);
        $decode->setAccessible(true);

        $original = random_bytes(20);
        $encoded  = $encode->invoke($this->provider, $original);
        $decoded  = $decode->invoke($this->provider, $encoded);

        $this->assertSame($original, $decoded);
    }

    // -----------------------------------------------------------------------

    private function generateCodeViaReflection(string $secret, int $counter): string
    {
        $ref    = new ReflectionClass($this->provider);
        $decode = $ref->getMethod('base32Decode');
        $decode->setAccessible(true);
        $keyBytes = $decode->invoke($this->provider, $secret);

        $generate = $ref->getMethod('generateCode');
        $generate->setAccessible(true);

        return $generate->invoke($this->provider, $keyBytes, $counter);
    }
}
