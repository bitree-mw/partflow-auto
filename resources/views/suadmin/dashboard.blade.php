@extends('layouts.suadmin')

@section('actions')
    <div class="row-actions">
        <a class="btn-secondary" href="{{ route('suadmin.users.create') }}">Add user</a>
        <a class="btn" href="{{ route('suadmin.sites.create') }}">Add site</a>
    </div>
@endsection

@section('content')
    <section class="suadmin-cards" aria-label="Summary">
        @foreach ($cards as $card)
            <article>
                <span class="suadmin-muted">{{ $card['label'] }}</span>
                <strong>{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</strong>
                <span class="suadmin-muted">{{ $card['detail'] }}</span>
            </article>
        @endforeach
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <h2>Recent activity</h2>
            <a class="btn-secondary" href="{{ route('suadmin.audit-logs.index') }}">Full audit log</a>
        </div>

        @unless ($auditReady)
            <p class="empty-state">The audit log table does not exist yet. Run the database migrations on this server to start recording activity.</p>
        @else
            @include('suadmin.partials.audit-table', ['logs' => $recentActivity])
        @endunless
    </section>
@endsection
