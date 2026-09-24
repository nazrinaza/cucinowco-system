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
    @php
        $beforePhotos = $siteVisit->photos->where('phase', 'before');
        $afterPhotos = $siteVisit->photos->where('phase', 'after');
    @endphp
    <section class="admin-card site-photo-section">
        <div class="card-head"><div><p>Private job gallery</p><h2>Match the same angle, before &amp; after</h2></div><small>Only signed-in team members can view these images.</small></div>
        <div class="site-photo-columns">
            <div class="site-photo-phase">
                <div class="site-photo-phase-head"><h3>01 · Capture before</h3><span>{{ $beforePhotos->count() }} / 30</span></div>
                <p class="muted">Photograph the area before work starts. Label the camera position and direction so the team can repeat the angle later.</p>
                <form method="post" action="{{ route('admin.site-visits.photos.store', $siteVisit) }}" enctype="multipart/form-data" class="site-photo-upload" data-site-photo-upload>@csrf
                    <input type="hidden" name="phase" value="before">
                    <label><span>Camera angle / location</span><input name="caption" maxlength="180" placeholder="e.g. Hall entrance facing the stage" required></label>
                    <label><span>Take or choose a before photo</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" required></label>
                    <small>JPG, PNG, WebP, HEIC or HEIF · maximum 8 MB per file. Large JPG/PNG/WebP photos are resized to 2,000 px.</small>
                    <button class="admin-button" type="submit">Save before angle</button>
                </form>
            </div>
            <div class="site-photo-phase">
                <div class="site-photo-phase-head"><h3>02 · Match after</h3><span>{{ $afterPhotos->count() }} / 30</span></div>
                <p class="muted">Choose the reference angle below, stand in the same position, then take the after photo before finishing the cleanup.</p>
                @if($beforePhotos->isEmpty())
                    <p class="site-photo-empty">Upload a before angle first to unlock after photos.</p>
                @else
                    <form method="post" action="{{ route('admin.site-visits.photos.store', $siteVisit) }}" enctype="multipart/form-data" class="site-photo-upload" data-site-photo-upload>@csrf
                        <input type="hidden" name="phase" value="after">
                        <fieldset class="site-photo-reference-choices"><legend>Select the matching before angle</legend>
                            @foreach($beforePhotos as $before)
                                <label class="site-photo-reference-choice"><input type="radio" name="before_photo_id" value="{{ $before->id }}" required>
                                    @if(in_array($before->mime_type, ['image/heic', 'image/heif']))
                                        <img data-photo-heic-preview src="{{ route('admin.site-visits.photos.show', [$siteVisit, $before]) }}" alt="HEIC reference angle #{{ $before->id }}" loading="lazy">
                                        <span class="site-photo-reference-heic" data-photo-heic-fallback hidden>HEIC angle #{{ $before->id }}</span>
                                    @else
                                        <img src="{{ route('admin.site-visits.photos.show', [$siteVisit, $before]) }}" alt="Reference angle: {{ $before->caption ?: 'Angle #'.$before->id }}" loading="lazy">
                                    @endif
                                    <strong>Angle #{{ $before->id }}<small>{{ $before->caption ?: 'Unlabelled legacy photo' }}</small></strong>
                                </label>
                            @endforeach
                        </fieldset>
                        <label><span>Take or choose the matching after photo</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" required></label>
                        <label><span>After note (optional)</span><input name="caption" maxlength="180" placeholder="e.g. Floor stain removed"></label>
                        <small>Maximum 8 MB per file. HEIC is kept in its original format on this hosting.</small>
                        <button class="admin-button" type="submit">Save paired after photo</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="site-photo-matched">
            <div class="site-photo-matched-head"><h3>Matched angles</h3><p>Use each before photo as the visual reference for its after shots.</p></div>
            @forelse($beforePhotos as $before)
                <div class="site-photo-pair">
                    <div class="site-photo-pair-before"><span>Before · angle #{{ $before->id }}</span>@include('admin.site-visits._photo-card', ['photo' => $before])</div>
                    <div class="site-photo-pair-after"><span>After · same angle</span><div class="site-photo-grid">
                        @forelse($afterPhotos->where('before_photo_id', $before->id) as $after)
                            @include('admin.site-visits._photo-card', ['photo' => $after])
                        @empty
                            <p class="site-photo-empty">After photo still needed from this angle.</p>
                        @endforelse
                    </div></div>
                </div>
            @empty
                <p class="site-photo-empty">No before angles yet.</p>
            @endforelse
            @if($afterPhotos->whereNull('before_photo_id')->isNotEmpty())
                <div class="site-photo-unpaired"><h3>Earlier after photos to pair</h3><p class="muted">Photos uploaded before pairing was added remain here until you choose their matching angle.</p>
                    <div class="site-photo-grid">@foreach($afterPhotos->whereNull('before_photo_id') as $after)
                        <div>@include('admin.site-visits._photo-card', ['photo' => $after])
                            <form method="post" action="{{ route('admin.site-visits.photos.pair', [$siteVisit, $after]) }}" class="site-photo-repair">@csrf @method('patch')
                                <select name="before_photo_id" required><option value="">Choose before angle</option>@foreach($beforePhotos as $before)<option value="{{ $before->id }}">#{{ $before->id }} · {{ $before->caption ?: 'Unlabelled photo' }}</option>@endforeach</select>
                                <button class="admin-button secondary" type="submit">Pair photo</button>
                            </form>
                        </div>
                    @endforeach</div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.admin>
