<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = Staff::withCount('bookings')->orderBy('name')->paginate(20);

        return view('admin.staff.index', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email', 'unique:staff,email'], 'phone' => ['nullable', 'string', 'max:30'], 'role' => ['required', 'string', 'max:60'], 'status' => ['required', 'in:available,assigned,leave,inactive']]);
        Staff::create($data);

        return back()->with('success', 'Team member added.');
    }
}
