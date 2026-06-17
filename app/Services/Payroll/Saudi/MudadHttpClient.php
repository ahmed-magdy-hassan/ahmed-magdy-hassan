<?php

declare(strict_types=1);

namespace App\Services\Payroll\Saudi;

use App\Contracts\MudadApiClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * HTTP client for the Mudad WPS (Wage Protection System) API.
 *
 * Wraps Laravel's HTTP client so tests can inject a fake Factory without
 * touching the Http facade.
 *
 * Config keys (mudad.*):
 *   base_url      — https://api.mudad.com.sa/v1 (no trailing slash)
 *   api_key       — Bearer token provided by Mudad
 *   timeout       — Per-request timeout in seconds (default 30)
 *   retry_times   — Retry attempts on connection failures (default 3)
 *   retry_delay_ms — Base sleep between retries in ms, doubles each round (default 200)
 *
 * Retries are triggered ONLY by network-layer errors (ConnectionException).
 * API-level errors (4xx / 5xx) are not retried — they surface as RuntimeException.
 */
final class MudadHttpClient implements MudadApiClientInterface
{
    private readonly string  $baseUrl;
    private readonly string  $apiKey;
    private readonly int     $timeout;
    private readonly int     $retryTimes;
    private readonly int     $retryDelayMs;
    private readonly Factory $http;

    public function __construct(
        ?Factory $http         = null,
        ?string  $baseUrl      = null,
        ?string  $apiKey       = null,
        ?int     $timeout      = null,
        ?int     $retryTimes   = null,
        ?int     $retryDelayMs = null,
    ) {
        $this->http         = $http         ?? app(Factory::class);
        $this->baseUrl      = rtrim($baseUrl      ?? (string) config('mudad.base_url', ''), '/');
        $this->apiKey       = $apiKey       ?? (string) config('mudad.api_key', '');
        $this->timeout      = $timeout      ?? (int)    config('mudad.timeout', 30);
        $this->retryTimes   = $retryTimes   ?? (int)    config('mudad.retry_times', 3);
        $this->retryDelayMs = $retryDelayMs ?? (int)    config('mudad.retry_delay_ms', 200);
    }

    /**
     * {@inheritDoc}
     *
     * Endpoint: POST {base_url}/wps/submissions
     * Payload:  multipart — sif_file (raw text), payroll_month (YYYY-MM), employer_id
     */
    public function submit(string $sifContent, string $payrollMonth, string $employerId): array
    {
        $response = $this->request()
            ->asMultipart()
            ->post("{$this->baseUrl}/wps/submissions", [
                ['name' => 'sif_file',      'contents' => $sifContent, 'filename' => "WPS_{$payrollMonth}.sif"],
                ['name' => 'payroll_month', 'contents' => $payrollMonth],
                ['name' => 'employer_id',   'contents' => $employerId],
            ]);

        $this->assertSuccessful($response, 'submit');

        return $response->json();
    }

    /**
     * {@inheritDoc}
     *
     * Endpoint: GET {base_url}/wps/submissions/{referenceId}
     */
    public function getStatus(string $referenceId): array
    {
        $response = $this->request()
            ->get("{$this->baseUrl}/wps/submissions/{$referenceId}");

        $this->assertSuccessful($response, 'getStatus');

        return $response->json();
    }

    /**
     * {@inheritDoc}
     *
     * Endpoint: DELETE {base_url}/wps/submissions/{referenceId}
     *
     * Returns false (not an exception) if the server responds 409 Conflict,
     * meaning the submission has already progressed past a cancellable state.
     */
    public function cancel(string $referenceId): bool
    {
        $response = $this->request()
            ->delete("{$this->baseUrl}/wps/submissions/{$referenceId}");

        if ($response->status() === 409) {
            return false;
        }

        $this->assertSuccessful($response, 'cancel');

        return true;
    }

    // -----------------------------------------------------------------------

    private function request(): PendingRequest
    {
        return $this->http
            ->withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->retry(
                $this->retryTimes,
                $this->retryDelayMs,
                fn (\Throwable $e) => $e instanceof ConnectionException,
            );
    }

    private function assertSuccessful(Response $response, string $operation): void
    {
        if ($response->failed()) {
            $status  = $response->status();
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException(
                "Mudad API [{$operation}] failed with HTTP {$status}: {$message}"
            );
        }
    }
}
