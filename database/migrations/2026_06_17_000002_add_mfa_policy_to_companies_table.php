<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRIST-198: Company-level MFA enforcement policy.
 *
 * mfa_required         — When true, all admin/HR users in this company must have
 *                        confirmed 2FA before accessing protected routes.
 *                        Enforced by the RequiresMfa middleware.
 *
 * mfa_required_roles   — JSON array of role slugs for which MFA is mandatory.
 *                        NULL means the policy applies to all roles when mfa_required=true.
 *                        Example: ["admin", "hr_manager", "payroll_manager"]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('mfa_required')->default(false)->after('name');
            $table->json('mfa_required_roles')->nullable()->after('mfa_required');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['mfa_required', 'mfa_required_roles']);
        });
    }
};
