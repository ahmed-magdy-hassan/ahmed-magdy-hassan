<?php

declare(strict_types=1);

namespace App\Services\Payroll\Saudi;

use App\Contracts\MudadApiClientInterface;
use App\Enums\Payroll\WpsSubmissionStatus;
use App\ValueObjects\Saudi\WpsSifRecord;
use App\ValueObjects\Saudi\WpsSubmission;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Orchestrates WPS payroll submission to the Saudi Mudad platform.
 *
 * Flow:
 *   1. Validate all employee SIF records (IBAN, employee ID, net salary).
 *   2. Confirm we are within the 30-day submission window.
 *   3. Generate the SIF file.
 *   4. POST to Mudad via MudadApiClientInterface::submit().
 *   5. Return a WpsSubmission value object with the response reference.
 *
 * Status polling is handled separately via pollStatus().
 *
 * The 30-day WPS window was shortened from 60 → 30 days effective March 2025.
 * Late submissions incur penalties of ~SAR 5,000/employee plus risk Qiwa access freeze.
 */
final class MudadSubmissionService
{
    private const SUBMISSION_WINDOW_DAYS = 30;

    public function __construct(
        private readonly WpsSifGenerator         $sifGenerator,
        private readonly MudadApiClientInterface $apiClient,
    ) {}

    /**
     * Validate, generate, and submit a WPS payroll for the given month.
     *
     * @param  WpsSifRecord[]  $records
     * @param  Carbon|null     $windowDeadline  Override the deadline for testing; defaults to payroll month + 30 days.
     *
     * @throws InvalidArgumentException on validation failure or expired window.
     */
    public function submit(
        string  $employerId,
        string  $employerBankId,
        string  $payrollMonth,
        array   $records,
        ?Carbon $windowDeadline = null,
    ): WpsSubmission {
        $this->assertWithinWindow($payrollMonth, $windowDeadline);
        $this->assertRecordsValid($records);

        $sifContent = $this->sifGenerator->generate($employerBankId, $payrollMonth, $records);

        $totalNet = array_sum(array_map(fn(WpsSifRecord $r) => $r->netSalary, $records));

        $submission = new WpsSubmission(
            id: $this->newUuid(),
            employerId: $employerId,
            payrollMonth: $payrollMonth,
            status: WpsSubmissionStatus::Pending,
            recordCount: count($records),
            totalNetSalary: $totalNet,
            mudadReferenceId: null,
            bankReferenceId: null,
            errorMessage: null,
            createdAt: Carbon::now(),
            submittedAt: null,
            completedAt: null,
        );

        $response = $this->apiClient->submit($sifContent, $payrollMonth, $employerId);

        return $submission->withStatus(
            status: WpsSubmissionStatus::Submitted,
            mudadReferenceId: $response['reference_id'] ?? null,
        );
    }

    /**
     * Polls Mudad for the current status of a submission and returns an updated snapshot.
     * If the submission is already in a final state, or has no reference ID, it is returned unchanged.
     */
    public function pollStatus(WpsSubmission $submission): WpsSubmission
    {
        if ($submission->isFinal() || $submission->mudadReferenceId === null) {
            return $submission;
        }

        $response = $this->apiClient->getStatus($submission->mudadReferenceId);
        $status   = WpsSubmissionStatus::from($response['status'] ?? WpsSubmissionStatus::Processing->value);

        return $submission->withStatus(
            status: $status,
            errorMessage: ($status === WpsSubmissionStatus::Rejected)
                ? ($response['message'] ?? null)
                : null,
        );
    }

    /**
     * Cancel a pending or submitted WPS submission. Returns the updated snapshot.
     *
     * @throws InvalidArgumentException if the submission is already in a final state.
     */
    public function cancel(WpsSubmission $submission): WpsSubmission
    {
        if ($submission->isFinal()) {
            throw new InvalidArgumentException(
                "Cannot cancel a {$submission->status->label()} submission."
            );
        }

        if ($submission->mudadReferenceId !== null) {
            $this->apiClient->cancel($submission->mudadReferenceId);
        }

        return $submission->withStatus(WpsSubmissionStatus::Cancelled);
    }

    // -----------------------------------------------------------------------

    private function assertWithinWindow(string $payrollMonth, ?Carbon $deadline): void
    {
        $windowEnd = $deadline
            ?? Carbon::parse($payrollMonth . '-01')->addDays(self::SUBMISSION_WINDOW_DAYS);

        if (Carbon::now()->isAfter($windowEnd)) {
            throw new InvalidArgumentException(
                "WPS submission window closed for {$payrollMonth}. "
                . "Deadline was {$windowEnd->toDateString()}."
            );
        }
    }

    private function assertRecordsValid(array $records): void
    {
        $errors = [];
        foreach ($records as $record) {
            foreach ($record->validationErrors() as $e) {
                $errors[] = $e;
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(
                'WPS validation failed: ' . implode(' | ', $errors)
            );
        }
    }

    private function newUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }
}
