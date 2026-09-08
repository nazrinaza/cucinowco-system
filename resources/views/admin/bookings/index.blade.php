<x-layouts.admin title="Bookings">
    <form class="admin-filters booking-filters" method="get">
        <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
        <select name="status">
            <option value="">All statuses</option>
            @foreach(['pending','confirmed','in_progress','completed','cancelled'] as $bookingStatus)
                <option value="{{ $bookingStatus }}" @selected($status === $bookingStatus)>{{ str($bookingStatus)->replace('_',' ')->title() }}</option>
            @endforeach
        </select>
        <button type="submit">Filter</button>
    </form>

    @php
        $monthParameters = fn ($month) => array_filter([
            'month' => $month->format('Y-m'),
            'status' => $status,
        ]);
    @endphp
    <section class="admin-card booking-calendar-card">
        <div class="booking-calendar-head">
            <div>
                <p class="admin-kicker">Monthly schedule</p>
                <h2>{{ $calendarMonth->format('F Y') }}</h2>
                <small>{{ number_format($calendarCounts->sum()) }} scheduled {{ str('booking')->plural($calendarCounts->sum()) }} this month</small>
            </div>
            <nav class="booking-calendar-nav" aria-label="Calendar month navigation">
                <a class="admin-button secondary" href="{{ route('admin.bookings.index', $monthParameters($calendarMonth->subMonth())) }}" aria-label="Previous month">&larr;</a>
                <a class="admin-button secondary calendar-today-link" href="{{ route('admin.bookings.index', array_filter(['month' => now()->format('Y-m'), 'status' => $status])) }}">Today</a>
                <a class="admin-button secondary" href="{{ route('admin.bookings.index', $monthParameters($calendarMonth->addMonth())) }}" aria-label="Next month">&rarr;</a>
            </nav>
        </div>
        <div class="booking-calendar-legend" aria-label="Booking status colours">
            <span><i class="calendar-dot confirmed"></i> Confirmed <strong>{{ $calendarCounts['confirmed'] }}</strong></span>
            <span><i class="calendar-dot in-progress"></i> In progress <strong>{{ $calendarCounts['in_progress'] }}</strong></span>
            <span><i class="calendar-dot completed"></i> Completed <strong>{{ $calendarCounts['completed'] }}</strong></span>
            <span><i class="calendar-dot pending"></i> Pending <strong>{{ $calendarCounts['pending'] }}</strong></span>
            <span><i class="calendar-dot cancelled"></i> Cancelled <strong>{{ $calendarCounts['cancelled'] }}</strong></span>
        </div>
        <div class="booking-calendar-weekdays" aria-hidden="true">
            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)<span>{{ $weekday }}</span>@endforeach
        </div>
        <div class="booking-calendar-grid">
            @foreach($calendarDays as $day)
                @php($dayBookings = $calendarBookings->get($day->format('Y-m-d'), collect()))
                <article @class([
                    'booking-calendar-day',
                    'outside-month' => !$day->isSameMonth($calendarMonth),
                    'is-today' => $day->isToday(),
                    'has-bookings' => $dayBookings->isNotEmpty(),
                ])>
                    <header>
                        <time datetime="{{ $day->format('Y-m-d') }}">{{ $day->day }}</time>
                        @if($dayBookings->isNotEmpty())<span>{{ $dayBookings->count() }}</span>@endif
                    </header>
                    <div class="booking-calendar-events">
                        @foreach($dayBookings->take(3) as $booking)
                            <div class="calendar-booking calendar-booking-{{ str($booking->status)->replace('_', '-') }}" role="img" aria-label="{{ $booking->booking_number }}, {{ $booking->customer->name }}, {{ $booking->scheduled_start->format('g:i A') }}, {{ str($booking->status)->replace('_', ' ')->title() }}" title="{{ $booking->booking_number }} — {{ $booking->customer->name }} — {{ str($booking->status)->replace('_', ' ')->title() }}">
                                <strong>{{ $booking->scheduled_start->format('g:i A') }}</strong>
                                <span>{{ $booking->customer->name }}</span>
                            </div>
                        @endforeach
                        @if($dayBookings->count() > 3)
                            <small>+{{ $dayBookings->count() - 3 }} more</small>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="booking-list-heading">
        <div><p class="admin-kicker">Booking management</p><h2>All booking details</h2></div>
    </div>
    <div class="booking-admin-list">
        @forelse($bookings as $booking)
            <article class="admin-card">
                <div class="booking-date booking-date-{{ str($booking->status)->replace('_', '-') }}">
                    @if($booking->scheduled_start)
                        <strong>{{ $booking->scheduled_start->format('d') }}</strong>
                        <span>{{ $booking->scheduled_start->format('M Y') }}</span>
                        <small>{{ $booking->scheduled_start->format('g:i A') }}</small>
                    @else
                        <strong>--</strong><span>Unscheduled</span>
                    @endif
                </div>
                <div class="booking-info">
                    <span class="status status-{{ $booking->status }}">{{ str($booking->status)->replace('_',' ')->title() }}</span>
                    <h2>{{ $booking->customer->name }}</h2>
                    <p>{{ $booking->service?->name ?? 'Cleaning service' }}</p>
                    <small>{{ $booking->service_address }}</small>
                    @if($booking->customer->email)
                        <form method="post" action="{{ route('admin.bookings.send',$booking) }}">
                            @csrf
                            <button class="booking-email-link" type="submit">Email confirmation{{ $booking->confirmation_sent_at ? ' again' : '' }}</button>
                        </form>
                    @endif
                </div>
                <form class="booking-edit" method="post" action="{{ route('admin.bookings.update',$booking) }}">
                    @csrf @method('patch')
                    <select name="status">
                        @foreach(['pending','confirmed','in_progress','completed','cancelled'] as $bookingStatus)
                            <option value="{{ $bookingStatus }}" @selected($booking->status === $bookingStatus)>{{ str($bookingStatus)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select>
                    <select name="staff_id">
                        <option value="">Unassigned</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected($booking->staff_id === $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="scheduled_start" value="{{ $booking->scheduled_start?->format('Y-m-d\TH:i') }}">
                    <input type="datetime-local" name="scheduled_end" value="{{ $booking->scheduled_end?->format('Y-m-d\TH:i') }}">
                    <button class="admin-button" type="submit">Save</button>
                </form>
            </article>
        @empty
            <div class="admin-card empty-cell">Bookings created from accepted quotes will appear here.</div>
        @endforelse
    </div>
    <div class="pagination-wrap">{{ $bookings->links() }}</div>
</x-layouts.admin>
