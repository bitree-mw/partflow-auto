@extends('layouts.suadmin')

@section('actions')
    <a class="btn-secondary" href="{{ route('suadmin.users.index') }}">Back to users</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ $user->exists ? route('suadmin.users.update', $user) : route('suadmin.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Full name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="username">Username</label>
                <input class="form-control" id="username" name="username" value="{{ old('username', $user->username) }}" placeholder="Generated from the email if blank" autocomplete="off">
                <x-form-error name="username" />
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="off">
                <x-form-error name="email" />
            </div>

            <div class="form-field">
                <label for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                <x-form-error name="phone" />
            </div>

            <div class="form-field">
                <label for="role_id">Role</label>
                <select class="form-control" id="role_id" name="role_id">
                    <option value="">No role (cannot use the system)</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                            {{ $role->name }}{{ in_array('*', $role->permissions ?? [], true) ? ' (administrator, full access)' : '' }}
                        </option>
                    @endforeach
                </select>
                <x-form-error name="role_id" />
            </div>

            <div class="form-field">
                <label for="site">Default site</label>
                <select class="form-control" id="site" name="site">
                    <option value="All sites">All sites</option>
                    @foreach ($sites as $siteName)
                        <option value="{{ $siteName }}" @selected(old('site', $defaultSite) === $siteName)>{{ $siteName }}</option>
                    @endforeach
                </select>
                <x-form-error name="site" />
            </div>

            <div class="form-field">
                <label for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" @unless ($user->exists) required @endunless minlength="8">
                <small>{{ $user->exists ? 'Leave blank to keep the current password. Changing it signs the user out everywhere.' : 'At least 8 characters.' }}</small>
                <x-form-error name="password" />
            </div>

            <div class="form-field">
                <label for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </div>

            @if ($user->exists)
                <div class="form-field full">
                    <label>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        Account is active
                    </label>
                    <small>Deactivated accounts are signed out everywhere and cannot sign in.</small>
                    <x-form-error name="is_active" />
                </div>
            @endif
        </div>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('suadmin.users.index') }}">Cancel</a>
            <button class="btn" type="submit">{{ $user->exists ? 'Save changes' : 'Create account' }}</button>
        </div>
    </form>
@endsection
