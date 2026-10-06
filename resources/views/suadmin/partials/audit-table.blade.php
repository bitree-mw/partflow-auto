<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Event</th>
                <th>Who</th>
                <th>What happened</th>
                <th>IP address</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td title="{{ $log->created_at?->toDayDateTimeString() }}">
                        {{ $log->created_at?->format('d M Y H:i') }}<br>
                        <span class="suadmin-muted">{{ $log->created_at?->diffForHumans() }}</span>
                    </td>
                    <td>
                        <span @class([
                            'status-pill',
                            'danger' => str_ends_with($log->event, '.failed') || str_ends_with($log->event, '.deleted'),
                            'warning' => str_ends_with($log->event, '.deactivated'),
                            'success' => str_ends_with($log->event, '.succeeded') || str_ends_with($log->event, '.created'),
                        ])>{{ $log->event }}</span>
                    </td>
                    <td>
                        {{ $log->actor_label ?? 'Unknown visitor' }}
                        @if ($log->actor_type === 'super_admin')
                            <br><span class="suadmin-muted">Super admin console</span>
                        @endif
                    </td>
                    <td>
                        {{ $log->description }}
                        @if (! empty($log->properties))
                            <details class="suadmin-details">
                                <summary>Details</summary>
                                <pre>{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @endif
                    </td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-state">No activity recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
