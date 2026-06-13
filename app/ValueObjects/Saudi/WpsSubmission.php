<?php

declare(strict_types=1);

namespace App\ValueObjects\Saudi;

use App\Enums\Payroll\WpsSubmissionStatus;
use Carbon\Carbon;

/**
 * Immutable snapshot of a WPS payroll-submission lifecycle.
 *
 * State machine:
 *   Pending → Submitted → Processing → Accepted
 *                      └→ Rejected
 *   Any non-final → Cancelled
 */
final readonly class WpsSubmission
{
    public function __construct(
        public string              $id,
        public string              $employerId,
        public string              $payrollMonth,   // YYYY-MM
        public WpsSubmissionStatus $status,
        public int                 $recordCount,
        public float               $totalNetSalary,
        public ?string             $mudadReferenceId,
        public ?string             $bankReferenceId,
        public ?string             $errorMessage,
        public Carbon              $createdAt,
        public ?Carbon             $submittedAt,
        public ?Carbon             $completedAt,
    ) {}

    public function isPending(): bool
    {
        return $this->status === WpsSubmissionStatus::Pending;
    }

    public function isSubmitted(): bool
    {
        return $this->status === WpsSubmissionStatus::Submitted;
    }

    public function isAccepted(): bool
    {
        return $this->status === WpsSubmissionStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === WpsSubmissionStatus::Rejected;
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /** Returns a new instance with the given status applied. */
    public function withStatus(
        WpsSubmissionStatus $status,
        ?string $mudadReferenceId = null,
        ?string $errorMessage     = null,
        ?Carbon $timestamp        = null,
    ): self {
        $ts = $timestamp ?? Carbon::now();

        return new self(
            id: $this->id,
            employerId: $this->employerId,
            payrollMonth: $this->payrollMonth,
            status: $status,
            recordCount: $this->recordCount,
            totalNetSalary: $this->totalNetSalary,
            mudadReferenceId: $mudadReferenceId ?? $this->mudadReferenceId,
            bankReferenceId: $this->bankReferenceId,
            errorMessage: $errorMessage ?? $this->errorMessage,
            createdAt: $this->createdAt,
            submittedAt: $status === WpsSubmissionStatus::Submitted && $this->submittedAt === null
                ? $ts
                : $this->submittedAt,
            completedAt: $status->isFinal() && $this->completedAt === null
                ? $ts
                : $this->completedAt,
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'employer_id'        => $this->employerId,
            'payroll_month'      => $this->payrollMonth,
            'status'             => $this->status->value,
            'status_label'       => $this->status->label(),
            'record_count'       => $this->recordCount,
            'total_net_salary'   => $this->totalNetSalary,
            'mudad_reference_id' => $this->mudadReferenceId,
            'bank_reference_id'  => $this->bankReferenceId,
            'error_message'      => $this->errorMessage,
            'created_at'         => $this->createdAt->toIso8601String(),
            'submitted_at'       => $this->submittedAt?->toIso8601String(),
            'completed_at'       => $this->completedAt?->toIso8601String(),
        ];
    }
}
