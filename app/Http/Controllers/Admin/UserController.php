<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['admin', 'office', 'field'])],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        User::create([...$data, 'is_active' => true]);

        return back()->with('success', 'Account created. Share the initial password securely and ask the user to change it.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['admin', 'office', 'field'])],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        if ($user->is($request->user()) && (! (bool) $data['is_active'] || $data['role'] !== 'admin')) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access.']);
        }

        if ($data['role'] !== 'admin' || ! (bool) $data['is_active']) {
            $otherAdmins = User::where('role', 'admin')->where('is_active', true)->whereKeyNot($user->id)->exists();
            if ($user->role === 'admin' && $user->is_active && ! $otherAdmins) {
                return back()->withErrors(['role' => 'Keep at least one active admin account.']);
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('success', 'Account updated.');
    }
}
