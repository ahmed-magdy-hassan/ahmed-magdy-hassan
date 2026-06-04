<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\Hr\DocumentType;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tracks document expiry dates (Iqama, passport, work visa/permit, contract)
 * and surfaces alertable entries against configurable advance-warning thresholds.
 *
 * Status tiers:
 *   lapsed   — already expired
 *   critical — expires within 7 days
 *   warning  — expires within 30 days
 *   upcoming — expires within 90 days
 *   valid    — expires beyond 90 days
 *   not_set  — no expiry date recorded
 */
final class DocumentExpiryService
{
    /** Default advance-warning thresholds in days (descending order used in alerts). */
    public const DEFAULT_THRESHOLDS = [90, 60, 30, 7];

    /**
     * Returns the full expiry status for every document type on an employee.
     */
    public function getEmployeeDocumentStatus(Employee $employee): array
    {
        $today = Carbon::today();

        return array_map(function (DocumentType $type) use ($employee, $today): array {
            $field  = $type->expiryField();
            $expiry = $employee->$field ? Carbon::parse($employee->$field) : null;

            if ($expiry === null) {
                return [
                    'document_type'  => $type->value,
                    'label'          => $type->label(),
                    'expiry_date'    => null,
                    'days_remaining' => null,
                    'status'         => 'not_set',
                ];
            }

            $daysRemaining = (int) $today->diffInDays($expiry, false);

            return [
                'document_type'  => $type->value,
                'label'          => $type->label(),
                'expiry_date'    => $expiry->toDateString(),
                'days_remaining' => $daysRemaining,
                'status'         => $this->resolveStatus($daysRemaining),
            ];
        }, DocumentType::cases());
    }

    /**
     * Returns employees (scoped to company) with at least one document
     * expiring within the given number of days from today.
     */
    public function getExpiringWithinDays(int $companyId, int $days): Collection
    {
        $today     = Carbon::today();
        $threshold = Carbon::today()->addDays($days);

        return Employee::where('company_id', $companyId)
            ->where(function ($q) use ($today, $threshold): void {
                foreach (DocumentType::cases() as $type) {
                    $q->orWhereBetween($type->expiryField(), [$today, $threshold]);
                }
            })
            ->get();
    }

    /**
     * Returns employees (scoped to company) with at least one already-lapsed document.
     */
    public function getLapsed(int $companyId): Collection
    {
        $today = Carbon::today();

        return Employee::where('company_id', $companyId)
            ->where(function ($q) use ($today): void {
                foreach (DocumentType::cases() as $type) {
                    $q->orWhere($type->expiryField(), '<', $today);
                }
            })
            ->get();
    }

    /**
     * Returns document entries for an employee where days_remaining <= any threshold.
     * Only the tightest threshold hit is reported per document.
     */
    public function getAlertableDocuments(
        Employee $employee,
        array $thresholds = self::DEFAULT_THRESHOLDS,
    ): array {
        $today      = Carbon::today();
        $thresholds = array_unique($thresholds);
        sort($thresholds);
        $alertable  = [];

        foreach (DocumentType::cases() as $type) {
            $field  = $type->expiryField();
            $expiry = $employee->$field ? Carbon::parse($employee->$field) : null;

            if ($expiry === null) {
                continue;
            }

            $daysRemaining = (int) $today->diffInDays($expiry, false);

            foreach ($thresholds as $threshold) {
                if ($daysRemaining <= $threshold) {
                    $alertable[] = [
                        'document_type'  => $type->value,
                        'label'          => $type->label(),
                        'expiry_date'    => $expiry->toDateString(),
                        'days_remaining' => $daysRemaining,
                        'status'         => $this->resolveStatus($daysRemaining),
                        'threshold_hit'  => $threshold,
                    ];
                    break;
                }
            }
        }

        return $alertable;
    }

    private function resolveStatus(int $daysRemaining): string
    {
        return match (true) {
            $daysRemaining < 0  => 'lapsed',
            $daysRemaining <= 7 => 'critical',
            $daysRemaining <= 30 => 'warning',
            $daysRemaining <= 90 => 'upcoming',
            default              => 'valid',
        };
    }
}
