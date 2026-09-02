<x-layouts.admin title="Create estimate" heading="Create estimate & quotation">
    <x-slot:actions><a href="{{ route('admin.quotes.index') }}" class="admin-button secondary">Back to quotes</a></x-slot:actions>
    @if($siteVisit)
        <div class="admin-context-banner">
            <div><span>Site visit</span><strong>{{ $siteVisit->reference_number }}</strong><p>{{ $siteVisit->customer->name }} &middot; {{ $siteVisit->service?->name }}</p></div>
            <a href="{{ route('admin.site-visits.show', $siteVisit) }}">View request &nearr;</a>
        </div>
    @endif
    <div class="admin-estimator-shell">
        <livewire:quote-estimator :site-visit="$siteVisit" />
    </div>
</x-layouts.admin>
