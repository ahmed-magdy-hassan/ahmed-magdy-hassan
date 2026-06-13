<?php

declare(strict_types=1);

namespace App\Enums\Payroll;

enum GosiNationality: string
{
    case SaudiNational = 'saudi_national';
    case Expatriate    = 'expatriate';

    public function label(): string
    {
        return match ($this) {
            self::SaudiNational => 'Saudi National',
            self::Expatriate    => 'Expatriate',
        };
    }
}
