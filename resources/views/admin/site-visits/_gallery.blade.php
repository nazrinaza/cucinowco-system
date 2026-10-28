<section class="admin-card site-photo-section">
    <div class="card-head"><div><p>Physical site visit</p><h2>Inspection photo gallery</h2></div><span>{{ $siteVisit->photos->count() }} / 60 photos</span></div>
    <div class="inspection-gallery-layout">
        <form method="post" action="{{ route('admin.site-visits.photos.store', $siteVisit) }}" enctype="multipart/form-data" class="site-photo-upload inspection-photo-upload" data-site-photo-upload>
            @csrf
            <h3>Capture site condition</h3>
            <p class="muted">Take clear photos of the areas, access points, surfaces, machinery requirements and any safety concerns observed during the assessment.</p>
            <label><span>Photo note (optional)</span><input name="caption" maxlength="180" placeholder="e.g. Loading bay access at Level 1"></label>
            <label><span>Take or choose a photo</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" capture="environment" required></label>
            <small>JPG, PNG, WebP, HEIC or HEIF · maximum 8 MB. Large supported images are resized to 2,000 px.</small>
            <button class="admin-button" type="submit">Upload photo</button>
        </form>
        <div class="inspection-photo-list">
            @forelse($siteVisit->photos->sortByDesc('created_at') as $photo)
                @include('admin.site-visits._photo-card', ['photo' => $photo])
            @empty
                <p class="site-photo-empty">No site-assessment photos have been uploaded.</p>
            @endforelse
        </div>
    </div>
</section>
