<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;
use App\Enums\Payroll\TerminationReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a company employee.
 *
 * Base columns (assumed in the core employees migration):
 *   id, company_id, name, email, hire_date, basic_salary, nationality,
 *   created_at, updated_at
 *
 * Extended via additive migrations:
 *   EOSB fields  — 2026_06_04_000001
 *   Egypt fields — 2026_06_13_000001
 *   GOSI fields  — 2026_06_13_000002
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        // Core
        'company_id',
        'name',
        'email',
        'hire_date',
        'basic_salary',
        'nationality',

        // EOSB (2026_06_04_000001)
        'eosb_amount',
        'eosb_termination_reason',
        'eosb_calculated_at',

        // Egypt (2026_06_13_000001)
        'eta_tax_id',
        'nosi_number',
        'nosi_insured_salary',
        'special_needs',
        'last_eta_monthly_withholding',
        'nosi_registered_at',

        // GOSI (2026_06_13_000002)
        'gosi_nationality',
        'gosi_scheme',
        'gosi_number',
        'gosi_registered_at',
        'gosi_insured_salary',
        'gosi_salary_updated_at',
    ];

    protected $casts = [
        'hire_date'                  => 'date',
        'basic_salary'               => 'float',
        'eosb_amount'                => 'float',
        'eosb_termination_reason'    => TerminationReason::class,
        'eosb_calculated_at'         => 'datetime',
        'nosi_insured_salary'        => 'float',
        'special_needs'              => 'boolean',
        'last_eta_monthly_withholding' => 'float',
        'nosi_registered_at'         => 'date',
        'gosi_nationality'           => GosiNationality::class,
        'gosi_scheme'                => GosiScheme::class,
        'gosi_registered_at'         => 'date',
        'gosi_insured_salary'        => 'float',
        'gosi_salary_updated_at'     => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
