<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditLogService
{
    public const ACTOR_CONTEXT_KEY = 'audit_actor';

    public const SUPER_ADMIN_ACTOR = 'super_admin';

    public const EVENT_GROUPS = [
        'auth' => 'Staff sign-ins',
        'suadmin' => 'Super admin access',
        'user' => 'User accounts',
        'role' => 'Roles',
        'site_access' => 'User site access',
        'site' => 'Sites',
        'settings' => 'Business settings',
    ];

    private const SECRET_KEYS = ['password', 'password_confirmation', 'remember_token', 'token', 'secret'];

    private ?bool $tableReady = null;

    /**
     * Append an audit entry. Failures are logged rather than thrown so auditing never blocks the action itself.
     */
    public function record(string $event, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): void
    {
        if (! $this->tableReady()) {
            return;
        }

        try {
            [$actorType, $actorUserId, $actorLabel] = $this->actor($actor);
            $request = app()->bound('request') ? request() : null;

            AuditLog::query()->create([
                'event' => $event,
                'actor_type' => $actorType,
                'actor_user_id' => $actorUserId,
                'actor_label' => $actorLabel ? Str::limit($actorLabel, 250, '') : null,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => Str::limit($description, 500, ''),
                'properties' => $this->withoutSecrets($properties) ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Audit log entry could not be written.', [
                'event' => $event,
                'exception_type' => class_basename($exception),
            ]);
        }
    }

    /**
     * Field-level differences between two attribute snapshots, with secrets reduced to a "changed" marker.
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;

            if ($this->normalise($old) === $this->normalise($value)) {
                continue;
            }

            $changes[$key] = $this->isSecret((string) $key)
                ? 'changed'
                : ['from' => $old, 'to' => $value];
        }

        return $changes;
    }

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return AuditLog::query()
            ->when(filled($filters['group'] ?? null), fn ($query) => $query->where('event', 'like', $filters['group'].'.%'))
            ->when(filled($filters['actor'] ?? null), fn ($query) => $query->where('actor_type', $filters['actor']))
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $query->where(fn ($inner) => $inner
                    ->where('description', 'like', $term)
                    ->orWhere('actor_label', 'like', $term)
                    ->orWhere('ip_address', 'like', $term));
            })
            ->when(filled($filters['from'] ?? null), fn ($query) => $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($query) => $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function recent(int $limit = 10)
    {
        if (! $this->tableReady()) {
            return collect();
        }

        return AuditLog::query()->latest('created_at')->latest('id')->limit($limit)->get();
    }

    public function countSince(string $event, Carbon $since): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        return AuditLog::query()->where('event', $event)->where('created_at', '>=', $since)->count();
    }

    public function tableReady(): bool
    {
        return $this->tableReady ??= Schema::hasTable('audit_logs');
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     */
    private function actor(?User $explicitActor = null): array
    {
        if (Context::get(self::ACTOR_CONTEXT_KEY) === self::SUPER_ADMIN_ACTOR) {
            return [self::SUPER_ADMIN_ACTOR, null, 'Super admin'];
        }

        $user = $explicitActor ?? auth()->user();

        if ($user instanceof User) {
            return ['user', (int) $user->getKey(), $user->username ? "{$user->name} ({$user->username})" : $user->name];
        }

        $request = app()->bound('request') ? request() : null;

        return $request?->route() ? ['guest', null, null] : ['system', null, 'System'];
    }

    private function withoutSecrets(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if ($this->isSecret((string) $key) && $value !== 'changed') {
                $properties[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $properties[$key] = $this->withoutSecrets($value);
            }
        }

        return $properties;
    }

    private function isSecret(string $key): bool
    {
        return in_array(Str::lower($key), self::SECRET_KEYS, true);
    }

    private function normalise(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode(Arr::sortRecursive($value));
        }

        return $value === null ? null : (string) $value;
    }
}
