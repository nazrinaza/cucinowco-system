<x-layouts.admin title="Site visits">
    <x-slot:actions><a href="{{ route('admin.quotes.create') }}" class="admin-button">Create estimate</a></x-slot:actions>
    <form class="admin-filters" method="get">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search reference, customer or phone">
        <select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <button type="submit">Filter</button>
    </form>

    <section class="admin-card site-visit-calendar-card">
        <div class="card-head site-visit-calendar-head">
            <div>
                <p>Planning view</p>
                <h2>Site visit calendar</h2>
            </div>
            <small>Select a visit to open its details and update its status.</small>
        </div>
        <div class="site-visit-calendar-legend" aria-label="Site visit status colours">
            <span><i class="site-visit-dot new"></i>New</span>
            <span><i class="site-visit-dot contacted"></i>Contacted</span>
            <span><i class="site-visit-dot scheduled"></i>Scheduled</span>
            <span><i class="site-visit-dot completed"></i>Completed</span>
            <span><i class="site-visit-dot cancelled"></i>Cancelled</span>
        </div>
        <div class="site-visit-calendar-wrap">
            <div
                class="site-visit-calendar"
                data-full-calendar
                data-events-url="{{ route('admin.site-visits.calendar-events', array_filter(['status' => request('status'), 'q' => request('q')])) }}"
            ></div>
            <p class="site-visit-calendar-error" data-calendar-error hidden>Calendar data could not be loaded. Refresh this page to try again.</p>
            <noscript><p class="site-visit-calendar-error">JavaScript is required for the calendar. The complete site visit list remains available below.</p></noscript>
        </div>
    </section>

    <div class="site-visit-list-heading">
        <p>Request register</p>
        <h2>All site visit requests</h2>
    </div>
    <section class="admin-card"><div class="table-wrap"><table><thead><tr><th>Request</th><th>Customer</th><th>Service</th><th>Preferred visit</th><th>Status</th><th>Quotation</th></tr></thead><tbody>
        @forelse($siteVisits as $siteVisit)
            <tr>
                <td><a href="{{ route('admin.site-visits.show', $siteVisit) }}">{{ $siteVisit->reference_number }}</a><small>{{ $siteVisit->created_at->format('d M Y, g:i A') }}</small></td>
                <td>{{ $siteVisit->customer->name }}<small>{{ $siteVisit->customer->company_name ?: $siteVisit->customer->phone }}</small></td>
                <td>{{ $siteVisit->service?->name ?? 'Service review required' }}<small>{{ str($siteVisit->space_type)->replace('_',' ')->title() }}</small></td>
                <td>{{ $siteVisit->preferred_date?->format('d M Y') ?? 'Flexible' }}<small>{{ str($siteVisit->preferred_time_slot)->title() }}</small></td>
                <td><span class="status status-{{ $siteVisit->status }}">{{ ucfirst($siteVisit->status) }}</span></td>
                <td>@if($siteVisit->quote)<a href="{{ route('admin.quotes.show', $siteVisit->quote) }}">{{ $siteVisit->quote->quote_number }}</a>@else<a href="{{ route('admin.quotes.create', ['site_visit' => $siteVisit->id]) }}">Create estimate</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-cell">New public site visit requests will appear here.</td></tr>
        @endforelse
    </tbody></table></div></section>
    <div class="pagination-wrap">{{ $siteVisits->links() }}</div>
</x-layouts.admin>
