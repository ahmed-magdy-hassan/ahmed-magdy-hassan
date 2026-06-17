<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Saudi;

use App\Services\Payroll\Saudi\MudadHttpClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MudadHttpClientTest extends TestCase
{
    private const BASE_URL = 'https://api.mudad.test';
    private const API_KEY  = 'test-bearer-token';

    private Factory $http;
    private MudadHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http   = new Factory();
        $this->client = new MudadHttpClient(
            http:     $this->http,
            baseUrl:  self::BASE_URL,
            apiKey:   self::API_KEY,
            timeout:  5,
            retryTimes:   1,
            retryDelayMs: 0,
        );
    }

    // -----------------------------------------------------------------------
    // submit()
    // -----------------------------------------------------------------------

    /** @test */
    public function submit_posts_to_correct_endpoint_and_returns_reference(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions' => Factory::response([
                'reference_id' => 'MUD-2026-001',
                'status'       => 'submitted',
                'message'      => null,
            ], 201),
        ]);

        $result = $this->client->submit('SIF_CONTENT', '2026-06', 'EMP-001');

        $this->assertSame('MUD-2026-001', $result['reference_id']);
        $this->assertSame('submitted', $result['status']);
        $this->assertNull($result['message']);
    }

    /** @test */
    public function submit_sends_bearer_token_in_authorization_header(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions' => Factory::response(['reference_id' => 'X', 'status' => 'submitted', 'message' => null]),
        ]);

        $this->client->submit('SIF', '2026-06', 'EMP-001');

        $this->http->assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer ' . self::API_KEY);
        });
    }

    /** @test */
    public function submit_sends_sif_file_payroll_month_and_employer_id(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions' => Factory::response(['reference_id' => 'X', 'status' => 'submitted', 'message' => null]),
        ]);

        $this->client->submit('MY_SIF_CONTENT', '2026-06', 'EMP-XYZ');

        $this->http->assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->isMultipart()
                && str_contains($body, 'MY_SIF_CONTENT')
                && str_contains($body, '2026-06')
                && str_contains($body, 'EMP-XYZ');
        });
    }

    /** @test */
    public function submit_throws_runtime_exception_on_api_error(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions' => Factory::response(
                ['message' => 'Invalid employer ID'],
                422
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/submit.*422/i');

        $this->client->submit('SIF', '2026-06', 'BAD-EMP');
    }

    /** @test */
    public function submit_strips_trailing_slash_from_base_url(): void
    {
        $client = new MudadHttpClient(
            http:    $this->http,
            baseUrl: self::BASE_URL . '/',  // trailing slash
            apiKey:  self::API_KEY,
        );

        $this->http->fake([
            self::BASE_URL . '/wps/submissions' => Factory::response(['reference_id' => 'X', 'status' => 'submitted', 'message' => null]),
        ]);

        $result = $client->submit('SIF', '2026-06', 'EMP-001');

        $this->assertArrayHasKey('reference_id', $result);
    }

    // -----------------------------------------------------------------------
    // getStatus()
    // -----------------------------------------------------------------------

    /** @test */
    public function get_status_calls_correct_endpoint_with_reference_id(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions/MUD-2026-001' => Factory::response([
                'reference_id'    => 'MUD-2026-001',
                'status'          => 'accepted',
                'message'         => null,
                'bank_reference_id' => 'BANK-ABC',
            ]),
        ]);

        $result = $this->client->getStatus('MUD-2026-001');

        $this->assertSame('accepted', $result['status']);
        $this->assertSame('BANK-ABC', $result['bank_reference_id']);
    }

    /** @test */
    public function get_status_throws_on_not_found(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions/UNKNOWN' => Factory::response(
                ['message' => 'Submission not found'],
                404
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/getStatus.*404/i');

        $this->client->getStatus('UNKNOWN');
    }

    // -----------------------------------------------------------------------
    // cancel()
    // -----------------------------------------------------------------------

    /** @test */
    public function cancel_returns_true_on_successful_deletion(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions/MUD-2026-001' => Factory::response(null, 204),
        ]);

        $this->assertTrue($this->client->cancel('MUD-2026-001'));
    }

    /** @test */
    public function cancel_returns_false_on_409_conflict(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions/MUD-2026-001' => Factory::response(
                ['message' => 'Cannot cancel: submission already processing'],
                409
            ),
        ]);

        $this->assertFalse($this->client->cancel('MUD-2026-001'));
    }

    /** @test */
    public function cancel_throws_on_unexpected_server_error(): void
    {
        $this->http->fake([
            self::BASE_URL . '/wps/submissions/MUD-2026-001' => Factory::response(
                ['message' => 'Internal server error'],
                500
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cancel.*500/i');

        $this->client->cancel('MUD-2026-001');
    }
}
