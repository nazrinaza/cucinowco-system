<x-layouts.admin title="Overview" heading="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}.">
    <div class="metric-grid">
        <article><span>Open site visits</span><strong>{{ number_format($metrics['site_visits']) }}</strong><a href="{{ route('admin.site-visits.index') }}">Review requests &rarr;</a></article>
        <article><span>Open bookings</span><strong>{{ number_format($metrics['bookings']) }}</strong><a href="{{ route('admin.bookings.index') }}">Open calendar &rarr;</a></article>
        <article><span>Outstanding</span><strong>RM {{ number_format($metrics['outstanding'], 2) }}</strong><a href="{{ route('admin.invoices.index') }}">Review invoices &rarr;</a></article>
        <article class="metric-dark"><span>Revenue this year</span><strong>RM {{ number_format($metrics['revenue'], 2) }}</strong><small>{{ number_format($metrics['quotes']) }} active quotes &middot; {{ number_format($metrics['customers']) }} customers</small></article>
    </div>
    <div class="dashboard-grid">
        <section class="admin-card"><div class="card-head"><div><p>Sales pipeline</p><h2>Recent quote requests</h2></div><a href="{{ route('admin.quotes.index') }}">View all</a></div>
            <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead><tbody>@forelse($recentQuotes as $quote)<tr><td><a href="{{ route('admin.quotes.show',$quote) }}">{{ $quote->quote_number }}</a><small>{{ $quote->created_at->diffForHumans() }}</small></td><td>{{ $quote->customer->name }}<small>{{ $quote->customer->phone }}</small></td><td><span class="status status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span></td><td>RM {{ number_format($quote->total,2) }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">Quotations created by the team will appear here.</td></tr>@endforelse</tbody></table></div>
        </section>
        <section class="admin-card"><div class="card-head"><div><p>Schedule</p><h2>Upcoming cleanings</h2></div><a href="{{ route('admin.bookings.index') }}">View calendar</a></div><div class="booking-list">@forelse($upcomingBookings as $booking)<article><time><strong>{{ $booking->scheduled_start->format('d') }}</strong><span>{{ $booking->scheduled_start->format('M') }}</span></time><div><h3>{{ $booking->customer->name }}</h3><p>{{ $booking->service?->name ?? 'Cleaning service' }} &middot; {{ $booking->scheduled_start->format('g:i A') }}</p><small>{{ $booking->staff?->name ?? 'Unassigned' }}</small></div></article>@empty<div class="empty-cell">No upcoming bookings yet.</div>@endforelse</div></section>
    </div>
</x-layouts.admin>
