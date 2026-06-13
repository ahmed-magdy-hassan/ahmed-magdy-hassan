<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for communicating with the Mudad WPS API.
 *
 * Mudad (مدد) is the Saudi platform for WPS (Wage Protection System) payroll
 * submissions operated through authorised Saudi banking channels.
 *
 * Implementations should handle authentication (Bearer token or mTLS),
 * base URL configuration, and HTTP retry logic.
 *
 * Expected response shapes are documented per method.
 */
interface MudadApiClientInterface
{
    /**
     * Submit a SIF file to Mudad for the given payroll month.
     *
     * @param  string $sifContent   Raw SIF file content (pipe-delimited text).
     * @param  string $payrollMonth YYYY-MM
     * @param  string $employerId   Mudad employer registration ID.
     *
     * @return array{
     *   reference_id: string,
     *   status: string,
     *   message: string|null,
     * }
     */
    public function submit(string $sifContent, string $payrollMonth, string $employerId): array;

    /**
     * Poll the current status of a previously submitted SIF.
     *
     * @param  string $referenceId The reference_id returned from submit().
     *
     * @return array{
     *   reference_id: string,
     *   status: string,
     *   message: string|null,
     *   bank_reference_id: string|null,
     * }
     */
    public function getStatus(string $referenceId): array;

    /**
     * Cancel a pending or submitted (not yet processed) WPS submission.
     *
     * Returns true on success, false if the submission cannot be cancelled
     * (e.g. already processing or in a final state).
     */
    public function cancel(string $referenceId): bool;
}
