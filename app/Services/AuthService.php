<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const WEB_SESSION_DEVICE = 'partflow-web-session';

    public function __construct(private readonly AuditLogService $auditLog) {}

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

        $this->auditLog->record('auth.registered', "Self-registered API account {$user->email}.", $user, [], $user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function login(array $data, ?string $ipAddress = null): array
    {
        $loginKey = array_key_exists('login', $data) ? 'login' : 'email';
        $login = Str::lower(trim((string) ($data[$loginKey] ?? '')));
        $channel = $this->channel($data);

        $user = User::query()
            ->where(function (Builder $query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->auditLog->record('auth.login.failed', "Failed {$channel} sign-in for \"".Str::limit($login, 100).'".', $user, [
                'login' => Str::limit($login, 100),
                'reason' => $user ? 'wrong_password' : 'unknown_account',
                'channel' => $channel,
            ]);

            throw ValidationException::withMessages([
                $loginKey => ['The provided login details are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            $this->auditLog->record('auth.login.failed', "Blocked {$channel} sign-in for disabled account {$user->name}.", $user, [
                'login' => Str::limit($login, 100),
                'reason' => 'account_disabled',
                'channel' => $channel,
            ]);

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

        $this->auditLog->record('auth.login.succeeded', "{$user->name} signed in ({$channel}).", $user, ['channel' => $channel], $user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        $this->auditLog->record('auth.logout', "{$user->name} signed out (api).", $user, ['channel' => 'api'], $user);
    }

    public function recordWebLogout(User $user): void
    {
        $this->auditLog->record('auth.logout', "{$user->name} signed out (web).", $user, ['channel' => 'web'], $user);
    }

    private function channel(array $data): string
    {
        return ($data['device_name'] ?? null) === self::WEB_SESSION_DEVICE ? 'web' : 'api';
    }
}
