<x-layouts.admin title="Referral QR" heading="Referral QR generator">
    <div class="metric-grid referral-metrics">
        <article><span>Active sources</span><strong>{{ number_format($summary['active']) }}</strong></article>
        <article><span>Tracked visits</span><strong>{{ number_format($summary['visits']) }}</strong></article>
        <article><span>Generated leads</span><strong>{{ number_format($summary['leads']) }}</strong></article>
    </div>

    <div class="referral-tools-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Create tracking source</p><h2>Generate a new referral QR</h2></div></div>
            <form method="post" action="{{ route('admin.referrals.store') }}" class="admin-form referral-create-form">
                @csrf
                <label><span>Referrer or campaign name</span><input name="name" value="{{ old('name') }}" maxlength="120" placeholder="e.g. Ahmad" required></label>
                <label><span>Channel</span><select name="channel" required>@foreach($channels as $value => $label)<option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Tracking code <em>optional</em></span><input name="code" value="{{ old('code') }}" maxlength="80" placeholder="Auto-generated, e.g. ahmad"><small>Lowercase letters, numbers and hyphens. This becomes ?ref=code.</small></label>
                <button class="admin-button" type="submit">Create referral QR</button>
            </form>
        </section>
        <section class="admin-card referral-guide">
            <div class="card-head"><div><p>How attribution works</p><h2>From scan to lead</h2></div></div>
            <ol><li><span>01</span>Customer scans or opens the referral link.</li><li><span>02</span>CuciNow remembers the valid source for 90 days.</li><li><span>03</span>The submitted site visit is tagged with that source.</li><li><span>04</span>No valid source is displayed as <strong>Direct</strong>.</li></ol>
        </section>
    </div>

    <section class="admin-card referral-register">
        <div class="card-head"><div><p>Tracking library</p><h2>Generated referral links</h2></div><small>QR uses high error correction so the centred CuciNow mark remains scannable.</small></div>
        <div class="referral-card-grid">
            @forelse($sources as $source)
                <article class="referral-card" data-referral-qr data-referral-url="{{ $source->tracking_url }}" data-referral-name="{{ $source->code }}" data-referral-logo="{{ asset('images/favicon-512x512.png') }}">
                    <div class="referral-qr-preview"><canvas width="720" height="720" aria-label="QR code for {{ $source->name }}"></canvas><span data-qr-loading>Generating QR…</span></div>
                    <div class="referral-card-content">
                        <div class="referral-card-title"><div><span>{{ str($source->channel)->replace('_', ' ')->title() }}</span><h3>{{ $source->name }}</h3></div><span class="status {{ $source->is_active ? 'status-completed' : 'status-cancelled' }}">{{ $source->is_active ? 'Active' : 'Paused' }}</span></div>
                        <code>{{ $source->tracking_url }}</code>
                        <div class="referral-stats"><p><strong>{{ number_format($source->visit_count) }}</strong><span>Visits</span></p><p><strong>{{ number_format($source->lead_count) }}</strong><span>Leads</span></p><p><strong>{{ $source->visit_count ? number_format(($source->lead_count / $source->visit_count) * 100, 1).'%' : '—' }}</strong><span>Conversion</span></p></div>
                        <div class="action-row referral-actions"><button type="button" class="admin-button secondary" data-copy-referral>Copy link</button><a class="admin-button" data-download-referral download="cucinow-ref-{{ $source->code }}.png">Download PNG</a></div>
                        <form method="post" action="{{ route('admin.referrals.update', $source) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $source->is_active ? 0 : 1 }}"><button class="referral-toggle" type="submit">{{ $source->is_active ? 'Pause this referral' : 'Reactivate referral' }}</button></form>
                        <small>Created by {{ $source->createdBy?->name ?? 'System' }} · {{ $source->created_at->format('d M Y') }}</small>
                    </div>
                </article>
            @empty
                <p class="empty-cell">Create your first agent or campaign referral above.</p>
            @endforelse
        </div>
    </section>
    <div class="pagination-wrap">{{ $sources->links() }}</div>
</x-layouts.admin>
