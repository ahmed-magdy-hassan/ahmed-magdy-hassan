<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'employee',
            'company_id'        => null,
            'remember_token'    => null,
        ];
    }

    /** User with 2FA setup started but not yet confirmed. */
    public function withPendingMfa(string $encryptedSecret, string $encryptedCodes): static
    {
        return $this->state([
            'two_factor_secret'         => $encryptedSecret,
            'two_factor_recovery_codes' => $encryptedCodes,
            'two_factor_confirmed_at'   => null,
        ]);
    }

    /** User with 2FA fully confirmed. two_factor_confirmed_at is set. */
    public function withConfirmedMfa(
        string $encryptedSecret,
        string $encryptedCodes,
    ): static {
        return $this->state([
            'two_factor_secret'         => $encryptedSecret,
            'two_factor_recovery_codes' => $encryptedCodes,
            'two_factor_confirmed_at'   => now(),
        ]);
    }
}
