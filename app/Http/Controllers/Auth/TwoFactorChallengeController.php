<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Services\Auth\AuthAuditService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;

/**
 * Handles the two-factor challenge step during login.
 *
 * After a user authenticates with their password, any endpoint behind the
 * requires.mfa middleware will return 403 until this challenge is passed.
 * The session / Sanctum token is already issued — this controller validates
 * the MFA step without re-authenticating.
 *
 * Route (requires auth:sanctum):
 *   POST /auth/two-factor-challenge
 */
final class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuthAuditService $audit,
    ) {}

    public function store(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user         = $request->user();
        $code         = $request->validated('code');
        $recoveryCode = $request->validated('recovery_code');

        if ($code !== null && $this->twoFactor->verify($user, $code)) {
            $this->audit->log('mfa.challenge.passed', $user->id, ['method' => 'totp']);

            return response()->json(['message' => 'Two-factor challenge passed.']);
        }

        if ($recoveryCode !== null && $this->twoFactor->verifyRecoveryCode($user, $recoveryCode)) {
            $this->audit->log('mfa.challenge.passed', $user->id, ['method' => 'recovery_code']);

            return response()->json([
                'message' => 'Recovery code accepted.',
                'remaining_recovery_codes' => $this->twoFactor->remainingRecoveryCodes($user),
            ]);
        }

        $this->audit->log('mfa.challenge.failed', $user->id);

        return response()->json(['message' => 'The provided two-factor code is invalid.'], 422);
    }
}
