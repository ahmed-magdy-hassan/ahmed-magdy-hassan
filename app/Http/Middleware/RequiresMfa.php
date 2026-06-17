<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce MFA for routes that handle sensitive HR/payroll data.
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'requires.mfa'])->group(...)
 *
 * Enforcement logic:
 *   1. If the company has mfa_required = true, ALL authenticated users must
 *      have confirmed 2FA regardless of role.
 *   2. If mfa_required_roles is set, only users with those roles are checked.
 *   3. Users without a company (e.g. super-admin) are exempted from company policy
 *      but can still be required by passing role checks via route definitions.
 */
final class RequiresMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $next($request);
        }

        if (!$company->mfa_required) {
            return $next($request);
        }

        // If mfa_required_roles is set, only enforce for listed roles
        $requiredRoles = $company->mfa_required_roles;
        if ($requiredRoles !== null && !in_array($user->role, $requiredRoles, strict: true)) {
            return $next($request);
        }

        if (!$user->hasConfirmedTwoFactor()) {
            return response()->json([
                'message'      => 'Multi-factor authentication is required for your account. '
                    . 'Enrol at POST /auth/two-factor then confirm at POST /auth/two-factor/confirm.',
                'mfa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
