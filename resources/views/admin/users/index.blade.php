<x-layouts.admin title="Team accounts" heading="Team accounts">
    <div class="detail-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Access & attribution</p><h2>People with logins</h2></div></div>
            <div class="user-account-list">
                @foreach($users as $user)
                    <form method="post" action="{{ route('admin.users.update', $user) }}" class="user-account-row">
                        @csrf @method('patch')
                        <div class="user-account-identity"><strong>{{ $user->name }}</strong><small>Last login: {{ $user->last_login_at?->format('d M Y, g:i A') ?? 'Never' }}</small></div>
                        <label><span>Name</span><input name="name" value="{{ $user->name }}" required></label>
                        <label><span>Email</span><input type="email" name="email" value="{{ $user->email }}" required></label>
                        <label><span>Phone</span><input name="phone" value="{{ $user->phone }}"></label>
                        <label><span>Role</span><select name="role"><option value="admin" @selected($user->role === 'admin')>Admin</option><option value="office" @selected($user->role === 'office')>Office</option><option value="field" @selected($user->role === 'field')>Field team</option></select></label>
                        <label><span>Access</span><select name="is_active"><option value="1" @selected($user->is_active)>Active</option><option value="0" @selected(!$user->is_active)>Inactive</option></select></label>
                        <details class="user-password-reset"><summary>Reset password</summary><label><span>New password</span><input type="password" name="password" minlength="12" autocomplete="new-password"></label><label><span>Confirm</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label></details>
                        <button class="admin-button" type="submit">Save account</button>
                    </form>
                @endforeach
            </div>
        </section>
        <aside class="detail-side">
            <section class="admin-card"><div class="card-head"><div><p>New login</p><h2>Add a team member</h2></div></div>
                <form method="post" action="{{ route('admin.users.store') }}" class="admin-form">@csrf
                    <label><span>Name</span><input name="name" value="{{ old('name') }}" required></label>
                    <label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" required></label>
                    <label><span>Phone (optional)</span><input name="phone" value="{{ old('phone') }}"></label>
                    <label><span>Role</span><select name="role"><option value="field">Field team — visits, photos, bookings</option><option value="office">Office — documents and operations</option><option value="admin">Admin — full access and accounts</option></select></label>
                    <label><span>Initial password (12+ characters)</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
                    <label><span>Confirm password</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
                    <button class="admin-button" type="submit">Create account</button>
                    <p class="muted">Each person needs their own login so photos and documents show who handled them. Share passwords privately.</p>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.admin>
