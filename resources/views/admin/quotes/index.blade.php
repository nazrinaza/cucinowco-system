<x-layouts.admin title="Quotes">
    <x-slot:actions><a href="{{ route('admin.quotes.create') }}" class="admin-button">Create estimate</a></x-slot:actions>
    <form class="admin-filters" method="get"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search reference, customer or phone"><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button type="submit">Filter</button></form>
    <section class="admin-card"><div class="table-wrap"><table>
        <thead><tr><th>Quote</th><th>Site visit session</th><th>Customer</th><th>Source</th><th>Service date</th><th>Status</th><th class="align-right">Estimate</th></tr></thead>
        <tbody>@forelse($quotes as $quote)<tr>
            <td><a href="{{ route('admin.quotes.show',$quote) }}">{{ $quote->quote_number }}</a><small>{{ $quote->created_at->format('d M Y, g:i A') }}</small></td>
            <td>@if($quote->siteVisit)<a href="{{ route('admin.site-visits.show', $quote->siteVisit) }}">{{ $quote->siteVisit->reference_number }}</a><small>Linked session</small>@else<span>Direct quotation</span><small>No site visit linked</small>@endif</td>
            <td>{{ $quote->customer->name }}<small>{{ $quote->customer->phone }}</small></td>
            <td><x-lead-source :label="$quote->referral_label" /></td>
            <td>{{ $quote->preferred_date?->format('d M Y') ?? 'Not set' }}<small>{{ str($quote->preferred_time_slot)->replace('_',' ')->title() }}</small></td>
            <td><span class="status status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span></td>
            <td class="align-right">RM {{ number_format($quote->total,2) }}</td>
        </tr>@empty<tr><td colspan="7" class="empty-cell">No quotes match this view.</td></tr>@endforelse</tbody>
    </table></div></section>
    <div class="pagination-wrap">{{ $quotes->links() }}</div>
</x-layouts.admin>
