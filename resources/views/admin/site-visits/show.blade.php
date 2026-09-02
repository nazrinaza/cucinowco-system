<x-layouts.admin title="Site visit {{ $siteVisit->reference_number }}" heading="{{ $siteVisit->reference_number }}">
    <x-slot:actions><div class="action-row">@if($siteVisit->quote)<a href="{{ route('admin.quotes.show', $siteVisit->quote) }}" class="admin-button">View quotation</a>@else<a href="{{ route('admin.quotes.create', ['site_visit' => $siteVisit->id]) }}" class="admin-button">Create estimate</a>@endif<a href="{{ route('admin.site-visits.index') }}" class="admin-button secondary">All requests</a></div></x-slot:actions>
    <div class="detail-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Public request</p><h2>{{ $siteVisit->service?->name ?? 'Site assessment' }}</h2></div><span class="status status-{{ $siteVisit->status }}">{{ ucfirst($siteVisit->status) }}</span></div>
            <dl class="detail-list">
                <div><dt>Customer</dt><dd>{{ $siteVisit->customer->name }}<br>{{ $siteVisit->customer->company_name }}</dd></div>
                <div><dt>Contact</dt><dd>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</dd></div>
                <div><dt>Space</dt><dd>{{ str($siteVisit->space_type)->replace('_',' ')->title() }}</dd></div>
                <div><dt>Preferred visit</dt><dd>{{ $siteVisit->preferred_date?->format('d M Y') }} &middot; {{ str($siteVisit->preferred_time_slot)->title() }}</dd></div>
                <div><dt>Site address</dt><dd>{{ $siteVisit->site_address }}<br>{{ $siteVisit->postcode }}</dd></div>
                <div><dt>Customer notes</dt><dd>{{ $siteVisit->customer_notes ?: 'No additional notes.' }}</dd></div>
            </dl>
        </section>
        <aside class="detail-side">
            <section class="admin-card"><div class="card-head"><div><p>Workflow</p><h2>Request status</h2></div></div><form method="post" action="{{ route('admin.site-visits.update', $siteVisit) }}" class="admin-form">@csrf @method('patch')<label><span>Status</span><select name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($siteVisit->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label><span>Internal notes</span><textarea name="internal_notes" rows="5">{{ $siteVisit->internal_notes }}</textarea></label><button class="admin-button" type="submit">Save changes</button></form></section>
            <section class="admin-card"><div class="card-head"><div><p>Contact</p><h2>{{ $siteVisit->customer->name }}</h2></div></div><p>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</p><a class="admin-button secondary full" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/','',$siteVisit->customer->phone) }}?text={{ urlencode('Hi '.$siteVisit->customer->name.', this is CuciNow regarding your free site visit '.$siteVisit->reference_number.'.') }}">Open WhatsApp</a></section>
        </aside>
    </div>
</x-layouts.admin>
