<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Hr\Egypt\EgyptPayrollController;
use App\Http\Controllers\Hr\EosbController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// Auth — unauthenticated (rate-limited to 6 attempts/minute)
// ============================================================================

Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('auth/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->name('password.email');

    Route::post('auth/reset-password',  [PasswordResetController::class, 'resetPassword'])
        ->name('password.reset');
});

// ============================================================================
// Auth — authenticated (Sanctum token required)
// ============================================================================

Route::middleware('auth:sanctum')->group(function (): void {

    // Two-factor authentication management
    Route::post  ('auth/two-factor',                [TwoFactorController::class, 'store'])->name('two-factor.store');
    Route::post  ('auth/two-factor/confirm',        [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('auth/two-factor',                [TwoFactorController::class, 'destroy'])->name('two-factor.destroy');
    Route::get   ('auth/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes.index');
    Route::post  ('auth/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes.regenerate');

    // Two-factor challenge (used during login when MFA is active)
    Route::post('auth/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->name('two-factor.challenge');

    // ========================================================================
    // HR API — MFA required for admin/HR roles (enforced by company policy)
    // ========================================================================

    Route::middleware('requires.mfa')->prefix('hr')->group(function (): void {

        // Saudi EOSB
        Route::get ('employees/{employee}/eosb/preview',  [EosbController::class, 'preview'])->name('eosb.preview');
        Route::post('employees/{employee}/eosb/finalize', [EosbController::class, 'finalize'])->name('eosb.finalize');

        // Egypt payroll
        Route::prefix('egypt')->group(function (): void {
            Route::post('employees/{employee}/payroll/calculate',     [EgyptPayrollController::class, 'calculate'])->name('egypt.payroll.calculate');
            Route::get ('employees/{employee}/labour-law-14',         [EgyptPayrollController::class, 'labourLaw14Entitlements'])->name('egypt.labour-law-14');
            Route::get ('companies/{company}/payroll/eta-form4',      [EgyptPayrollController::class, 'etaForm4'])->name('egypt.eta-form4');
        });
    });
});
