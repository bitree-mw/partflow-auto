@extends('layouts.suadmin')

@section('actions')
    <a class="btn" href="{{ route('suadmin.users.create') }}">Add user</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('suadmin.users.index') }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, username or email..." aria-label="Search users">
                <select name="role_id" aria-label="Filter by role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) ($filters['role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
                <select name="status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('suadmin.users.index') }}">Reset</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Default site</th>
                        <th>Last sign-in</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user['name'] }}</strong>
                                <br><span class="suadmin-muted">{{ $user['username'] }} · {{ $user['email'] }}</span>
                            </td>
                            <td>
                                {{ $user['role'] }}
                                @if ($user['is_administrator'])
                                    <br><span class="status-pill warning">Administrator</span>
                                @endif
                            </td>
                            <td>{{ $user['default_site'] }}</td>
                            <td>{{ $user['last_login'] }}</td>
                            <td><span @class(['status-pill', 'inactive' => ! $user['is_active']])>{{ $user['is_active'] ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn-secondary" href="{{ route('suadmin.users.edit', $user['id']) }}">Edit</a>
                                    @if ($user['is_active'])
                                        <form method="POST" action="{{ route('suadmin.users.deactivate', $user['id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                class="btn-secondary"
                                                type="submit"
                                                data-confirm-title="Deactivate account?"
                                                data-confirm="Deactivate {{ $user['name'] }}? They are signed out everywhere and can no longer sign in."
                                                data-confirm-label="Deactivate"
                                            >Deactivate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No user accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $users->links() }}
        </div>
    </section>
@endsection
