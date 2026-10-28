<figure class="site-photo-card">
    <a href="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" data-gallery-preview data-photo-url="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" data-photo-caption="{{ $photo->caption ?: $photo->original_name }}" data-photo-notes="{{ $photo->notes }}" data-photo-uploader="{{ $photo->uploadedBy?->name ?? 'Former team member' }}" data-photo-date="{{ $photo->created_at->format('d M Y, g:i A') }}" data-photo-mime="{{ $photo->mime_type }}" aria-haspopup="dialog">
        @if(in_array($photo->mime_type, ['image/heic', 'image/heif']))
            <img data-photo-heic-preview src="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" alt="Site assessment: {{ $photo->caption ?: $photo->original_name }}" loading="lazy">
            <span class="site-photo-heic" data-photo-heic-fallback hidden>HEIC preview unavailable here · open original</span>
        @else
            <img src="{{ route('admin.site-visits.photos.show', [$siteVisit, $photo]) }}" alt="Site assessment: {{ $photo->caption ?: $photo->original_name }}" loading="lazy">
        @endif
    </a>
    <figcaption>
        <strong>{{ $photo->caption ?: $photo->original_name }}</strong>
        @if($photo->notes)<span class="site-photo-note">{{ $photo->notes }}</span>@endif
        <small>{{ $photo->uploadedBy?->name ?? 'Former team member' }} · {{ $photo->created_at->format('d M Y, g:i A') }}</small>
    </figcaption>
    @can('manage-users')
        <form method="post" action="{{ route('admin.site-visits.photos.destroy', [$siteVisit, $photo]) }}" onsubmit="return confirm('Remove this photo?')">
            @csrf @method('delete')
            <button type="submit" class="site-photo-delete">Remove</button>
        </form>
    @endcan
</figure>
