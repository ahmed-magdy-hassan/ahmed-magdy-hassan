<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRIST-189: Saudi GOSI social-insurance fields for employees.
 *
 * gosi_nationality  — 'saudi_national' | 'expatriate' (mirrors GosiNationality enum)
 * gosi_scheme       — 'old' | 'new'  (mirrors GosiScheme; new = post-July 2025 entrant)
 * gosi_number       — GOSI registration number (up to 20 chars)
 * gosi_registered_at — Date the employee was first registered with GOSI
 * gosi_insured_salary — Last insured salary reported to GOSI (used to detect changes)
 * gosi_salary_updated_at — When the insured salary was last reported; used to flag
 *                          the 15-day reporting window on subsequent changes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('gosi_nationality', 20)->nullable()->after('nationality');
            $table->string('gosi_scheme', 10)->nullable()->after('gosi_nationality');
            $table->string('gosi_number', 20)->nullable()->after('gosi_scheme');
            $table->date('gosi_registered_at')->nullable()->after('gosi_number');
            $table->decimal('gosi_insured_salary', 10, 2)->nullable()->after('gosi_registered_at');
            $table->date('gosi_salary_updated_at')->nullable()->after('gosi_insured_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'gosi_nationality',
                'gosi_scheme',
                'gosi_number',
                'gosi_registered_at',
                'gosi_insured_salary',
                'gosi_salary_updated_at',
            ]);
        });
    }
};
