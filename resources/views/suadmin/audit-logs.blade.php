@extends('layouts.suadmin')

@section('content')
    <section class="data-panel">
        @unless ($ready)
            <p class="empty-state">The audit log table does not exist yet. Run the database migrations on this server to start recording activity.</p>
        @else
            <div class="panel-toolbar">
                <form class="filter-form" method="GET" action="{{ route('suadmin.audit-logs.index') }}">
                    <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search description, person or IP..." aria-label="Search audit log">
                    <select name="group" aria-label="Filter by area">
                        <option value="">All areas</option>
                        @foreach ($groups as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['group'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="actor" aria-label="Filter by who">
                        <option value="">Anyone</option>
                        <option value="user" @selected(($filters['actor'] ?? '') === 'user')>Staff accounts</option>
                        <option value="super_admin" @selected(($filters['actor'] ?? '') === 'super_admin')>Super admin</option>
                        <option value="guest" @selected(($filters['actor'] ?? '') === 'guest')>Not signed in</option>
                        <option value="system" @selected(($filters['actor'] ?? '') === 'system')>System</option>
                    </select>
                    <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" aria-label="From date">
                    <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" aria-label="To date">
                    <button class="btn-secondary" type="submit">Filter</button>
                    <a class="btn-secondary" href="{{ route('suadmin.audit-logs.index') }}">Reset</a>
                </form>
            </div>

            @include('suadmin.partials.audit-table', ['logs' => $logs])

            <div class="pagination-wrap">
                {{ $logs->links() }}
            </div>
        @endunless
    </section>
@endsection
