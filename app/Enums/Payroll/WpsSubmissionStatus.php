<?php

declare(strict_types=1);

namespace App\Enums\Payroll;

enum WpsSubmissionStatus: string
{
    case Pending    = 'pending';
    case Submitted  = 'submitted';
    case Processing = 'processing';
    case Accepted   = 'accepted';
    case Rejected   = 'rejected';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Submitted  => 'Submitted to Mudad',
            self::Processing => 'Processing',
            self::Accepted   => 'Accepted',
            self::Rejected   => 'Rejected',
            self::Cancelled  => 'Cancelled',
        };
    }

    /** A final status means no further state transitions are expected. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Cancelled], true);
    }
}
