<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Password-only access to /suadmin, kept in the session separately from staff authentication.
 */
class SuperAdminAuthService
{
    private const AUTHENTICATED_AT = 'suadmin.authenticated_at';

    private const LAST_ACTIVITY_AT = 'suadmin.last_activity_at';

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function isConfigured(): bool
    {
        return filled(config('suadmin.password_hash'));
    }

    public function attempt(string $password, Session $session): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $valid = Hash::check($password, (string) config('suadmin.password_hash'));
        } catch (Throwable) {
            // A malformed hash in .env must fail closed.
            $valid = false;
        }

        if (! $valid) {
            $this->auditLog->record('suadmin.login.failed', 'Failed super admin sign-in attempt.');

            return false;
        }

        $session->migrate(true);
        $session->put(self::AUTHENTICATED_AT, now()->getTimestamp());
        $session->put(self::LAST_ACTIVITY_AT, now()->getTimestamp());

        $this->markRequestAsSuperAdmin();
        $this->auditLog->record('suadmin.login.succeeded', 'Super admin signed in.');

        return true;
    }

    /**
     * Validates the session against idle and absolute limits and refreshes the activity timestamp.
     */
    public function check(Session $session): bool
    {
        $authenticatedAt = (int) $session->get(self::AUTHENTICATED_AT, 0);
        $lastActivityAt = (int) $session->get(self::LAST_ACTIVITY_AT, 0);

        if (! $this->isConfigured() || $authenticatedAt === 0) {
            return false;
        }

        $now = now()->getTimestamp();
        $idleExpired = $now - $lastActivityAt > config('suadmin.idle_timeout_minutes') * 60;
        $sessionExpired = $now - $authenticatedAt > config('suadmin.max_session_minutes') * 60;

        if ($idleExpired || $sessionExpired) {
            $this->forget($session);

            return false;
        }

        $session->put(self::LAST_ACTIVITY_AT, $now);
        $this->markRequestAsSuperAdmin();

        return true;
    }

    public function logout(Session $session): void
    {
        $this->auditLog->record('suadmin.logout', 'Super admin signed out.');
        $this->forget($session);
        $session->regenerateToken();
    }

    private function forget(Session $session): void
    {
        $session->forget([self::AUTHENTICATED_AT, self::LAST_ACTIVITY_AT]);
    }

    private function markRequestAsSuperAdmin(): void
    {
        Context::add(AuditLogService::ACTOR_CONTEXT_KEY, AuditLogService::SUPER_ADMIN_ACTOR);
    }
}
