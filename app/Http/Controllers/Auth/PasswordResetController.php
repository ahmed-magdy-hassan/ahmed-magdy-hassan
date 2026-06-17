<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\AuthAuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Self-service password reset via email token (HRIST-198).
 *
 * Routes (unauthenticated — no auth middleware):
 *   POST /auth/forgot-password   → forgotPassword()
 *   POST /auth/reset-password    → resetPassword()
 *
 * Rate limiting is applied at the route level (throttle:6,1 middleware)
 * to prevent brute-force enumeration of reset tokens.
 */
final class PasswordResetController extends Controller
{
    public function __construct(private readonly AuthAuditService $audit) {}

    /**
     * Send a password-reset link to the provided email address.
     * Always returns 200 to prevent user enumeration; the response message
     * is translated by Laravel's lang files.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Deliberately vague — do not reveal whether the email is registered
        $this->audit->log('password.reset.requested', 0, ['email_hash' => hash('sha256', $request->input('email'))]);

        return response()->json([
            'message' => 'If that email is registered, a password reset link has been sent.',
        ]);
    }

    /**
     * Reset the password using the token from the email link.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        $this->audit->log('password.reset.completed', 0, ['email_hash' => hash('sha256', $request->input('email'))]);

        return response()->json(['message' => __($status)]);
    }
}
