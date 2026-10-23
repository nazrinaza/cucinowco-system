<x-layouts.agent title="Marketing tools">
    <div class="agent-section-head"><div><p class="agent-kicker">CREATED BY HQ. SHARED BY YOU.</p><h1>Your next great introduction.</h1><p>Approved visuals and ready-to-share words, with your referral link built into captions.</p></div></div>
    <nav class="agent-filters" aria-label="Content categories"><a href="{{ route('agents.media') }}">All content</a>@foreach(['social', 'poster', 'video', 'caption', 'guide'] as $category)<a href="{{ route('agents.media', ['category' => $category]) }}" @class(['active' => request('category') === $category])>{{ ucfirst($category) }}</a>@endforeach</nav>
    <div class="agent-media-grid">@forelse($media as $item)
        @php($caption = str_replace('{ReferralLink}', Auth::guard('agent')->user()->referralSource->tracking_url, $item->caption ?? ''))
        <article class="agent-card agent-media-item">
            @if(in_array($item->mime_type, ['image/jpeg', 'image/png', 'image/webp']))<img class="agent-media-image" loading="lazy" src="{{ route('agents.media.preview', $item) }}" alt="{{ $item->title }}">@else<div class="agent-media-placeholder"><span>{{ strtoupper($item->category) }}</span><strong>Clean.<br>Starts.<br>Now.</strong></div>@endif
            <div class="agent-media-copy"><span class="agent-category">{{ ucfirst($item->category) }}</span><h2>{{ $item->title }}</h2><p>{{ $item->description }}</p>@if($item->expires_at)<small>Available until {{ $item->expires_at->format('d M Y') }}</small>@endif
                @if($caption)<label class="agent-caption-label">Ready-to-share caption<textarea readonly rows="5">{{ $caption }}</textarea></label><button type="button" class="agent-button agent-button-light" data-agent-copy="{{ $caption }}">Copy caption</button>@endif
                @if($item->path)<a class="agent-button" href="{{ route('agents.media.download', $item) }}">Download {{ strtoupper(pathinfo($item->original_name, PATHINFO_EXTENSION)) }} · {{ number_format($item->size / 1024 / 1024, 1) }} MB ↓</a>@endif
            </div>
        </article>
    @empty<div class="agent-empty">HQ is preparing content for this category. Check back soon.</div>@endforelse</div><div class="pagination-wrap">{{ $media->links() }}</div>
</x-layouts.agent>
