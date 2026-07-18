<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => isset($data['username']) ? Str::lower($data['username']) : null,
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $token = $user->createToken(
            $data['device_name'] ?? 'partflow-auto-api'
        )->plainTextToken;

        $user->load('role');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function login(array $data, ?string $ipAddress = null): array
    {
        $loginKey = array_key_exists('login', $data) ? 'login' : 'email';
        $login = Str::lower(trim((string) ($data[$loginKey] ?? '')));

        $user = User::query()
            ->where(function (Builder $query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                $loginKey => ['The provided login details are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                $loginKey => ['This account has been disabled.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ])->save();

        $token = $user->createToken(
            $data['device_name'] ?? 'partflow-auto-api'
        )->plainTextToken;

        $user->load('role');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
