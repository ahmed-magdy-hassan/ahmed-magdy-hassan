<?php

declare(strict_types=1);

namespace App\Enums\Payroll;

/**
 * GOSI registration scheme for Saudi nationals.
 *
 * The New Social Insurance Law (effective 2025-07-01) introduced a lower-rate
 * scheme for employees who join GOSI on or after that date. Employees already
 * registered before that date remain on the Old scheme for the life of their
 * service unless they voluntarily switch (pending future GOSI guidance).
 *
 * Expatriates are always on the Expatriate track (no annuities, hazards only)
 * regardless of this enum — GosiContributionService handles that distinction.
 */
enum GosiScheme: string
{
    case Old = 'old';  // Pre-2025-07-01 entrants
    case New = 'new';  // 2025-07-01+ new entrants (New Social Insurance Law)

    public function label(): string
    {
        return match ($this) {
            self::Old => 'Old Scheme (pre-July 2025)',
            self::New => 'New Scheme (from July 2025)',
        };
    }
}
