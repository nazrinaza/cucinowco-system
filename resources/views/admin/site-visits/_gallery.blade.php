<section class="admin-card site-photo-section">
    <div class="card-head"><div><p>Physical site visit</p><h2>Inspection photo gallery</h2></div><span>{{ $siteVisit->photos->count() }} / 60 photos</span></div>
    <div class="inspection-gallery-layout">
        <form method="post" action="{{ route('admin.site-visits.photos.store', $siteVisit) }}" enctype="multipart/form-data" class="site-photo-upload inspection-photo-upload" data-site-photo-upload>
            @csrf
            <h3>Capture site condition</h3>
            <p class="muted">Take clear photos of the areas, access points, surfaces, machinery requirements and any safety concerns observed during the assessment.</p>
            <label><span>Area / location</span><input name="caption" maxlength="180" placeholder="e.g. Loading bay at Level 1" required></label>
            <label><span>Inspection note / observation</span><textarea name="notes" rows="4" maxlength="1000" placeholder="Describe the condition, access issue, measurement, equipment need or safety concern shown in this photo." required></textarea></label>
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
    <dialog class="site-photo-modal" data-site-photo-modal aria-labelledby="site-photo-modal-title">
        <div class="site-photo-modal-shell">
            <button type="button" class="site-photo-modal-close" data-gallery-modal-close aria-label="Close photo preview">&times;</button>
            <div class="site-photo-modal-media">
                <img data-gallery-modal-image alt="">
                <p data-gallery-modal-fallback hidden>Preview is not supported by this browser. Open the original image instead.</p>
            </div>
            <div class="site-photo-modal-info">
                <p>Captured site condition</p>
                <h3 id="site-photo-modal-title" data-gallery-modal-caption></h3>
                <div class="site-photo-modal-notes"><span>Inspection note / observation</span><p data-gallery-modal-notes></p></div>
                <dl><div><dt>Uploaded by</dt><dd data-gallery-modal-uploader></dd></div><div><dt>Uploaded</dt><dd data-gallery-modal-date></dd></div></dl>
                <a class="admin-button secondary" data-gallery-modal-original target="_blank" rel="noopener">Open original image</a>
            </div>
        </div>
    </dialog>
</section>
