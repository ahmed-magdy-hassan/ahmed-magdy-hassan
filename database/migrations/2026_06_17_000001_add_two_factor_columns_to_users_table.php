<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRIST-198: TOTP-based two-factor authentication fields for the users table.
 *
 * two_factor_secret          — AES-encrypted TOTP secret (set when 2FA is initiated)
 * two_factor_recovery_codes  — AES-encrypted JSON array of bcrypt-hashed one-time codes
 * two_factor_confirmed_at    — Timestamp when the user verified their first TOTP code;
 *                              NULL means 2FA was initiated but not yet confirmed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
