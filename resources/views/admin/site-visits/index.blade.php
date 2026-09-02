<x-layouts.admin title="Site visits">
    <x-slot:actions><a href="{{ route('admin.quotes.create') }}" class="admin-button">Create estimate</a></x-slot:actions>
    <form class="admin-filters" method="get">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search reference, customer or phone">
        <select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <button type="submit">Filter</button>
    </form>
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
