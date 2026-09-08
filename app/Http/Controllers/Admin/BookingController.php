<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])],
        ]);
        $status = $data['status'] ?? null;
        $calendarMonth = isset($data['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $data['month'])
            : CarbonImmutable::now()->startOfMonth();
        $calendarStart = $calendarMonth->startOfWeek();
        $calendarEnd = $calendarMonth->endOfMonth()->endOfWeek();
        $calendarDays = collect(range(0, (int) $calendarStart->diffInDays($calendarEnd)))
            ->map(fn (int $offset): CarbonImmutable => $calendarStart->addDays($offset));

        $calendarBookingCollection = Booking::with(['customer', 'service', 'staff'])
            ->whereBetween('scheduled_start', [$calendarStart->startOfDay(), $calendarEnd->endOfDay()])
            ->when($status, fn ($query, string $selectedStatus) => $query->where('status', $selectedStatus))
            ->orderBy('scheduled_start')
            ->get();
        $calendarBookings = $calendarBookingCollection
            ->groupBy(fn (Booking $booking): string => $booking->scheduled_start->format('Y-m-d'));
        $monthBookings = $calendarBookingCollection
            ->filter(fn (Booking $booking): bool => $booking->scheduled_start->format('Y-m') === $calendarMonth->format('Y-m'));
        $calendarCounts = collect(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])
            ->mapWithKeys(fn (string $bookingStatus): array => [$bookingStatus => $monthBookings->where('status', $bookingStatus)->count()]);

        $bookings = Booking::with(['customer', 'service', 'staff'])
            ->when($status, fn ($query, string $selectedStatus) => $query->where('status', $selectedStatus))
            ->orderByRaw('scheduled_start IS NULL')
            ->orderBy('scheduled_start')
            ->paginate(20)
            ->withQueryString();
        $staff = Staff::where('status', '!=', 'inactive')->orderBy('name')->get();

        return view('admin.bookings.index', compact(
            'bookings',
            'staff',
            'status',
            'calendarMonth',
            'calendarDays',
            'calendarBookings',
            'calendarCounts',
        ));
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
