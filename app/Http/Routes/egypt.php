<?php

/**
 * Egypt payroll routes.
 *
 * Include in routes/api.php:
 *   require __DIR__ . '/../app/Http/Routes/egypt.php';
 *
 * Or register via RouteServiceProvider:
 *   Route::prefix('api')->middleware('api')->group(base_path('app/Http/Routes/egypt.php'));
 *
 * ============================================================
 * ENDPOINT REFERENCE
 * ============================================================
 *
 * ── Per-employee ─────────────────────────────────────────────
 *
 * POST /api/hr/egypt/employees/{employee}/payroll/calculate
 *   Calculate one employee's monthly payroll (preview — no DB write).
 *   Body:  { gross_salary: float, company_headcount?: int, tax_year?: int }
 *   200:   EgyptPayrollResult
 *
 * GET  /api/hr/egypt/employees/{employee}/labour-law-14
 *   Return Labour Law 14/2025 entitlements (leave, maternity, notice, increment).
 *   200:   { years_of_service, annual_leave_days, ... }
 *
 * ── Company ──────────────────────────────────────────────────
 *
 * POST /api/hr/egypt/companies/{company}/payroll/run
 *   Run payroll for all employees supplied in the request body.
 *   Body:  CompanyPayrollRunRequest
 *   201:   EgyptPayrollRunResult
 *
 * GET  /api/hr/egypt/companies/{company}/payroll/eta-form4
 *   Generate ETA Form 4 quarterly withholding return.
 *   Query: ?year=YYYY&quarter=1|2|3|4
 *   200:   EtaForm4Report
 *
 * GET  /api/hr/egypt/companies/{company}/payroll/nosi-report
 *   Generate NOSI Form 1 monthly contribution declaration.
 *   Query: ?year=YYYY&month=1-12
 *   200:   NosiMonthlyReport
 * ============================================================
 */

use App\Http\Controllers\Hr\Egypt\EgyptCompanyPayrollController;
use App\Http\Controllers\Hr\Egypt\EgyptPayrollController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr/egypt')->middleware(['auth:sanctum'])->group(function (): void {

    // ── Per-employee ─────────────────────────────────────────
    Route::prefix('employees/{employee}')->group(function (): void {
        Route::post('payroll/calculate',  [EgyptPayrollController::class, 'calculate']);
        Route::get('labour-law-14',       [EgyptPayrollController::class, 'labourLaw14Entitlements']);
    });

    // ── Company ──────────────────────────────────────────────
    Route::prefix('companies/{company}')->group(function (): void {
        Route::post('payroll/run',         [EgyptCompanyPayrollController::class, 'run']);
        Route::get('payroll/eta-form4',    [EgyptCompanyPayrollController::class, 'etaForm4']);
        Route::get('payroll/nosi-report',  [EgyptCompanyPayrollController::class, 'nosiMonthlyReport']);
    });
});
