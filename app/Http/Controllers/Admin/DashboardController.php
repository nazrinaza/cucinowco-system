<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Subscriber;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'quotes' => Quote::whereIn('status', ['draft', 'sent', 'viewed'])->count(),
            'bookings' => Booking::whereIn('status', ['pending', 'confirmed', 'in_progress'])->count(),
            'outstanding' => Invoice::whereIn('status', ['sent', 'partial', 'overdue'])->sum('balance'),
            'revenue' => Invoice::whereYear('issued_at', now()->year)->sum('amount_paid'),
            'customers' => Customer::count(),
            'subscribers' => Subscriber::where('status', 'subscribed')->count(),
        ];

        $recentQuotes = Quote::with('customer')->latest()->limit(6)->get();
        $upcomingBookings = Booking::with(['customer', 'service', 'staff'])->where('scheduled_start', '>=', now())->orderBy('scheduled_start')->limit(6)->get();

        return view('admin.dashboard', compact('metrics', 'recentQuotes', 'upcomingBookings'));
    }
}
