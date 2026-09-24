<x-layouts.admin title="Site visit {{ $siteVisit->reference_number }}" heading="{{ $siteVisit->reference_number }}">
    <x-slot:actions><div class="action-row">@can('manage-documents')@if($siteVisit->quote)<a href="{{ route('admin.quotes.show', $siteVisit->quote) }}" class="admin-button">View quotation</a>@else<a href="{{ route('admin.quotes.create', ['site_visit' => $siteVisit->id]) }}" class="admin-button">Create estimate</a>@endif @endcan<a href="{{ route('admin.site-visits.index') }}" class="admin-button secondary">All requests</a></div></x-slot:actions>
    <div class="detail-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Public request</p><h2>{{ $siteVisit->clean_types_label }}</h2></div><span class="status status-{{ $siteVisit->status }}">{{ ucfirst($siteVisit->status) }}</span></div>
            <dl class="detail-list">
                <div><dt>Customer</dt><dd>{{ $siteVisit->customer->name }}<br>{{ $siteVisit->customer->company_name }}</dd></div>
                <div><dt>Contact</dt><dd>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</dd></div>
                <div><dt>Space</dt><dd>{{ $siteVisit->space_label }}</dd></div>
                <div><dt>Preferred visit</dt><dd>{{ $siteVisit->preferred_date?->format('d M Y') }} &middot; {{ str($siteVisit->preferred_time_slot)->title() }}</dd></div>
                <div><dt>Site address</dt><dd>{{ $siteVisit->site_address ?: 'To be collected when confirming the visit.' }}<br>{{ $siteVisit->postcode }}</dd></div>
                <div><dt>Customer notes</dt><dd>{{ $siteVisit->customer_notes ?: 'No additional notes.' }}</dd></div>
            </dl>
        </section>
        <aside class="detail-side">
            <section class="admin-card"><div class="card-head"><div><p>Workflow</p><h2>Request status</h2></div></div><form method="post" action="{{ route('admin.site-visits.update', $siteVisit) }}" class="admin-form">@csrf @method('patch')<label><span>Status</span><select name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($siteVisit->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label><span>Internal notes</span><textarea name="internal_notes" rows="5">{{ $siteVisit->internal_notes }}</textarea></label><button class="admin-button" type="submit">Save changes</button></form></section>
            <section class="admin-card"><div class="card-head"><div><p>Contact</p><h2>{{ $siteVisit->customer->name }}</h2></div></div><p>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</p><a class="admin-button secondary full" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/','',$siteVisit->customer->phone) }}?text={{ urlencode('Hi '.$siteVisit->customer->name.', this is CuciNow regarding your free site visit '.$siteVisit->reference_number.'.') }}">Open WhatsApp</a></section>
        </aside>
    </div>
    <section class="admin-card site-photo-section">
        <div class="card-head"><div><p>Private job gallery</p><h2>Before &amp; after photos</h2></div><small>Only signed-in team members can view these images.</small></div>
        <div class="site-photo-columns">
            @foreach(['before' => 'Before cleanup', 'after' => 'After cleanup'] as $phase => $label)
                <div class="site-photo-phase">
                    <div class="site-photo-phase-head"><h3>{{ $label }}</h3><span>{{ $siteVisit->photos->where('phase', $phase)->count() }} / 30</span></div>
                    <p class="muted">{{ $phase === 'before' ? 'Document the site before work starts.' : 'Upload after the cleanup, before marking the booking completed.' }}</p>
                    <div class="site-photo-grid">
                        @forelse($siteVisit->photos->where('phase', $phase) as $photo)
                            <figure class="site-photo-card">
                                <a href="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" target="_blank" rel="noopener">@if(in_array($photo->mime_type, ['image/heic', 'image/heif']))<span class="site-photo-heic">Open HEIC photo</span>@else<img src="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" alt="{{ ucfirst($phase) }} cleanup photo{{ $photo->caption ? ': '.$photo->caption : '' }}" loading="lazy">@endif</a>
                                <figcaption><strong>{{ $photo->caption ?: $photo->original_name }}</strong><small>{{ $photo->uploadedBy?->name ?? 'Former team member' }} · {{ $photo->created_at->format('d M Y, g:i A') }}</small></figcaption>
                                @can('manage-users')<form method="post" action="{{ route('admin.site-visits.photos.destroy', [$siteVisit, $photo]) }}" onsubmit="return confirm('Remove this photo?')">@csrf @method('delete')<button type="submit" class="site-photo-delete">Remove</button></form>@endcan
                            </figure>
                        @empty
                            <p class="site-photo-empty">No {{ $phase }} photos yet.</p>
                        @endforelse
                    </div>
                    <form method="post" action="{{ route('admin.site-visits.photos.store', $siteVisit) }}" enctype="multipart/form-data" class="site-photo-upload">@csrf
                        <input type="hidden" name="phase" value="{{ $phase }}">
                        <label><span>Take or choose a photo</span><input type="file" name="photo" accept="image/*" required></label>
                        <label><span>Caption (optional)</span><input name="caption" maxlength="180" placeholder="e.g. Main hall entrance"></label>
                        <small>JPG, PNG, WebP or HEIC · max 8 MB per photo</small>
                        <button class="admin-button" type="submit">Upload {{ $phase }} photo</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.admin>
