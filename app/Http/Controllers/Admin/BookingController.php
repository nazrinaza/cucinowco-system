<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = Booking::with(['customer', 'service', 'staff'])->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))->orderByRaw('scheduled_start IS NULL')->orderBy('scheduled_start')->paginate(20)->withQueryString();
        $staff = Staff::where('status', '!=', 'inactive')->orderBy('name')->get();

        return view('admin.bookings.index', compact('bookings', 'staff'));
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])],
            'staff_id' => ['nullable', 'exists:staff,id'], 'scheduled_start' => ['nullable', 'date'], 'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
        ]);
        $booking->update($data);

        return back()->with('success', 'Booking updated.');
    }
}
