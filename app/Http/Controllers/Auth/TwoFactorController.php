<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Services\Auth\AuthAuditService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Manages the TOTP two-factor authentication lifecycle for the authenticated user.
 *
 * Routes (all require auth:sanctum):
 *   POST   /auth/two-factor                    → store()   initiate 2FA setup
 *   POST   /auth/two-factor/confirm            → confirm() confirm with first TOTP code
 *   DELETE /auth/two-factor                    → destroy() disable 2FA
 *   GET    /auth/two-factor/recovery-codes     → recoveryCodes()
 *   POST   /auth/two-factor/recovery-codes     → regenerateRecoveryCodes()
 */
final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService  $twoFactor,
        private readonly AuthAuditService  $audit,
    ) {}

    /**
     * Initiate 2FA enrolment: generate a TOTP secret and recovery codes.
     * The caller must scan the QR code and then call confirm() to activate.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $credentials = $this->twoFactor->enable($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log('mfa.setup_initiated', $request->user()->id);

        return response()->json($credentials->toArray(), 201);
    }

    /**
     * Confirm 2FA enrolment by verifying the first TOTP code from the authenticator app.
     */
    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        if (!$this->twoFactor->confirm($request->user(), $request->validated('code'))) {
            return response()->json(['message' => 'The provided code is invalid.'], 422);
        }

        $this->audit->log('mfa.confirmed', $request->user()->id);

        return response()->json(['message' => 'Two-factor authentication has been enabled.']);
    }

    /**
     * Disable 2FA. Requires the current TOTP code to prevent account takeover
     * if an attacker gains temporary access to an authenticated session.
     */
    public function destroy(TwoFactorConfirmRequest $request): JsonResponse
    {
        if (!$this->twoFactor->verify($request->user(), $request->validated('code'))) {
            return response()->json(['message' => 'The provided code is invalid.'], 422);
        }

        $this->twoFactor->disable($request->user());
        $this->audit->log('mfa.disabled', $request->user()->id);

        return response()->json(['message' => 'Two-factor authentication has been disabled.']);
    }

    /**
     * Return the number of remaining (unused) recovery codes.
     * The actual code values are not returned — they are shown only at enrolment.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasConfirmedTwoFactor()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        return response()->json([
            'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
        ]);
    }

    /**
     * Generate a new set of recovery codes. Invalidates all existing codes.
     * Requires the current TOTP code to authorise the regeneration.
     */
    public function regenerateRecoveryCodes(TwoFactorConfirmRequest $request): JsonResponse
    {
        if (!$this->twoFactor->verify($request->user(), $request->validated('code'))) {
            return response()->json(['message' => 'The provided code is invalid.'], 422);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());
        $this->audit->log('mfa.recovery_codes.regenerated', $request->user()->id);

        return response()->json(['recovery_codes' => $codes], 201);
    }
}
