<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Egypt-market fields to the employees table.
 *
 * Covers HRIST-191 (NOSI + ETA tax identifiers) and HRIST-192 (special-needs flag
 * for the 45-day leave entitlement under Labour Law No. 14/2025).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // ETA (tax authority) registration number — required for Form 4 filing
            $table->string('eta_tax_id')->nullable()->after('gosi_number');

            // NOSI (social insurance) registration number
            $table->string('nosi_number')->nullable()->after('eta_tax_id');

            // Declared NOSI-insurable salary — may differ from basic_salary
            // (e.g. if employee has variable pay components excluded from NOSI base)
            $table->decimal('nosi_insured_salary', 10, 2)->nullable()->after('nosi_number');

            // Special-needs flag — entitles the employee to 45 annual leave days
            // per Labour Law No. 14/2025, Art. 47
            $table->boolean('special_needs')->default(false)->after('nosi_insured_salary');

            // Snapshot of last ETA monthly withholding (for payslip display / reconciliation)
            $table->decimal('last_eta_monthly_withholding', 10, 2)->nullable()->after('special_needs');

            // Date employee was registered with NOSI (must be within 15 days of hire)
            $table->date('nosi_registered_at')->nullable()->after('last_eta_monthly_withholding');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'eta_tax_id',
                'nosi_number',
                'nosi_insured_salary',
                'special_needs',
                'last_eta_monthly_withholding',
                'nosi_registered_at',
            ]);
        });
    }
};
