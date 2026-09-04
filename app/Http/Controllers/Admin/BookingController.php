<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        $booking->fill($data);
        $shouldConfirm = $booking->status === 'confirmed'
            && ($booking->isDirty('status') || $booking->isDirty('scheduled_start') || $booking->isDirty('scheduled_end'));
        $booking->save();

        $confirmationQueued = $shouldConfirm && $this->queueConfirmation($booking);

        return back()->with('success', $confirmationQueued ? 'Booking updated and confirmation email queued.' : 'Booking updated.');
    }

    public function send(Booking $booking): RedirectResponse
    {
        if (! $booking->customer()->value('email')) {
            return back()->with('error', 'Add a customer email address before sending this confirmation.');
        }

        $this->queueConfirmation($booking);

        return back()->with('success', 'Booking confirmation email queued for delivery.');
    }

    private function queueConfirmation(Booking $booking): bool
    {
        $booking->load(['customer', 'service', 'quote.items']);
        if (! $booking->customer->email) {
            return false;
        }

        $booking->update(['confirmation_sent_at' => now()]);
        Mail::to($booking->customer->email, $booking->customer->name)
            ->queue(new BookingConfirmationMail($booking));

        return true;
    }
}
