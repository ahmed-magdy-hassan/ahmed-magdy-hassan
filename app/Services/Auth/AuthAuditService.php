<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Records security-relevant authentication events.
 *
 * Auth events logged (HRIST-198 acceptance criterion):
 *   mfa.setup_initiated        — user starts 2FA enrolment
 *   mfa.confirmed              — user confirms first TOTP code
 *   mfa.disabled               — user or admin disables 2FA
 *   mfa.challenge.passed       — login challenge passed (method: totp | recovery_code)
 *   mfa.challenge.failed       — login challenge failed (bad code)
 *   mfa.recovery_codes.regenerated — user regenerated recovery codes
 *   password.reset.requested   — forgot-password email dispatched
 *   password.reset.completed   — password successfully changed via reset token
 *
 * Currently writes to the application log. Swap to a dedicated audit_logs
 * table by extending this service with a DB writer when audit-search is needed.
 */
final class AuthAuditService
{
    public function __construct(private readonly Request $request) {}

    public function log(string $event, int $userId, array $context = []): void
    {
        Log::info("auth_audit:{$event}", array_merge([
            'user_id'    => $userId,
            'ip'         => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ], $context));
    }
}
