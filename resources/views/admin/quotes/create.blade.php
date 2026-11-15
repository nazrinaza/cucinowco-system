<x-layouts.admin title="Create estimate" heading="Create estimate & quotation">
    <x-slot:actions><a href="{{ route('admin.quotes.index') }}" class="admin-button secondary">Back to quotes</a></x-slot:actions>
    @if($siteVisit)
        <div class="admin-context-banner">
            <div><span>Site visit</span><strong>{{ $siteVisit->reference_number }}</strong><p>{{ $siteVisit->customer->name }} &middot; {{ $siteVisit->service?->name }} &middot; Source: {{ $siteVisit->referral_label }}</p></div>
            <a href="{{ route('admin.site-visits.show', $siteVisit) }}">View request &nearr;</a>
            @if($siteVisit->promo_discount_percent)<p class="promo-badge" style="flex-basis:100%">Customer used agent code {{ $siteVisit->promo_code }}: apply a {{ rtrim(rtrim(number_format($siteVisit->promo_discount_percent, 2), '0'), '.') }}% discount in the quotation.</p>@endif
        </div>
    @endif
    <div class="admin-estimator-shell">
        <livewire:quote-estimator :site-visit="$siteVisit" />
    </div>
</x-layouts.admin>
