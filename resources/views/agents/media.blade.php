<x-layouts.agent title="Marketing tools">
    <div class="agent-section-head"><div><p class="agent-kicker">CREATED BY HQ. SHARED BY YOU.</p><h1>Your next great introduction.</h1><p>Approved visuals and ready-to-share words, with your referral link built in.</p></div></div>

    {{-- Link generator --}}
    @if($referralUrl)
        <section class="agent-card mt-linkgen" data-link-builder data-public-url="{{ $publicUrl }}" data-ref="{{ \Illuminate\Support\Str::after($referralUrl, 'ref=') }}" aria-labelledby="linkgen-title">
            <div class="mt-linkgen-head">
                <div><p class="agent-kicker">PAGE LINKS</p><h2 id="linkgen-title">Link to any CuciNow page</h2><p>Sending someone to a specific service? Pick the page or paste its address, and we add your referral code.</p></div>
            </div>
            <div class="agent-form mt-linkgen-form">
                <div class="agent-form-row">
                    <label>Choose a page<select data-link-page>@foreach($pages as $path => $label)<option value="{{ $path }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Or paste a CuciNow link<input type="url" data-link-paste inputmode="url" placeholder="{{ $publicUrl }}/services/…"><small data-link-error class="mt-linkgen-error" aria-live="polite"></small></label>
                </div>
                <label>Your link<input type="text" readonly data-link-output value="{{ $referralUrl }}"></label>
                <div class="mt-actions">
                    <button type="button" class="agent-button" data-link-copy>Copy link</button>
                    <x-agent-share-menu text-from="[data-link-output]" :ref-url="$referralUrl" title="CuciNow" />
                </div>
            </div>
        </section>
    @endif

    {{-- Library toolbar --}}
    <div class="mt-toolbar">
        <nav class="mt-crumbs" aria-label="Folders">
            <a href="{{ route('agents.media') }}" @class(['is-current' => ! $folder && ! $category])>Library</a>
            @foreach($trail as $crumb)<span aria-hidden="true">›</span><a href="{{ route('agents.media', ['folder' => $crumb->id]) }}" @class(['is-current' => $loop->last]) @if($loop->last) aria-current="page" @endif>{{ $crumb->name }}</a>@endforeach
            @if($category)<span aria-hidden="true">›</span><span class="is-current">{{ ucfirst($category) }}</span>@endif
        </nav>
        <div class="mt-view" role="group" aria-label="Layout">
            <button type="button" data-view="grid" aria-pressed="true" title="Grid view"><span aria-hidden="true">▦</span><span class="sr-only">Grid view</span></button>
            <button type="button" data-view="list" aria-pressed="false" title="List view"><span aria-hidden="true">☰</span><span class="sr-only">List view</span></button>
        </div>
    </div>
    <nav class="agent-filters" aria-label="Content type"><a href="{{ route('agents.media', array_filter(['folder' => $folder?->id])) }}" @class(['active' => ! $category])>All types</a>@foreach(['social', 'poster', 'video', 'caption', 'guide'] as $type)<a href="{{ route('agents.media', ['category' => $type]) }}" @class(['active' => $category === $type])>{{ ucfirst($type) }}</a>@endforeach</nav>

    @if($folder)
        <a class="mt-back" href="{{ route('agents.media', array_filter(['folder' => $folder->parent_id])) }}">← Back</a>
    @endif

    {{-- Folders --}}
    @if($subfolders->isNotEmpty())
        <div class="mt-folders">
            @foreach($subfolders as $sub)
                @php($n = (int) ($counts[$sub->id] ?? 0))
                <a class="mt-folder" href="{{ route('agents.media', ['folder' => $sub->id]) }}"><span class="mt-folder-icon" aria-hidden="true"></span><strong>{{ $sub->name }}</strong><small>{{ $n ? $n.' item'.($n === 1 ? '' : 's') : 'Open folder' }}</small></a>
            @endforeach
        </div>
    @endif

    {{-- Items --}}
    <div class="agent-media-grid" data-media-list>@forelse($media as $item)
        @php($caption = str_replace('{ReferralLink}', $referralUrl ?? '', $item->caption ?? ''))
        @php($ext = $item->path ? strtoupper(pathinfo($item->original_name, PATHINFO_EXTENSION)) : null)
        <article class="agent-card agent-media-item">
            <div class="mt-thumb">
                @if($item->isImage())
                    <img class="agent-media-image" loading="lazy" src="{{ route('agents.media.preview', $item) }}" alt="{{ $item->title }}">
                @elseif($item->isVideo())
                    <video class="agent-media-image" controls playsinline preload="metadata" src="{{ route('agents.media.preview', $item) }}#t=0.1" aria-label="{{ $item->title }}"></video>
                @else
                    <div class="agent-media-placeholder"><span>{{ strtoupper($item->category) }}</span><strong>Clean.<br>Starts.<br>Now.</strong></div>
                @endif
            </div>
            <div class="agent-media-copy">
                <span class="agent-category">{{ ucfirst($item->category) }}@if($ext) · {{ $ext }} · {{ number_format($item->size / 1024 / 1024, 1) }} MB @endif</span>
                <h2>{{ $item->title }}</h2>
                @if($item->description)<p>{{ $item->description }}</p>@endif
                @if($item->expires_at)<small>Available until {{ $item->expires_at->format('d M Y') }}</small>@endif
                @if($caption)<label class="agent-caption-label">Ready-to-share caption<textarea readonly rows="4">{{ $caption }}</textarea></label>@endif
                <div class="mt-actions">
                    @if($caption)<button type="button" class="agent-button agent-button-light" data-agent-copy="{{ $caption }}">Copy caption</button>@endif
                    <x-agent-share-menu :text="$caption ?: (string) $referralUrl" :ref-url="$referralUrl" :title="$item->title"
                        :image-url="$item->isImage() ? \Illuminate\Support\Facades\URL::temporarySignedRoute('agents.media.shared', now()->addDays(30), $item) : null"
                        :file="$item->path ? route('agents.media.download', $item) : null" :file-name="$item->original_name" :file-type="$item->mime_type" />
                    @if($item->path)<a class="agent-button" href="{{ route('agents.media.download', $item) }}">Download ↓</a>@endif
                </div>
            </div>
        </article>
    @empty
        @if($subfolders->isEmpty())<div class="agent-empty">{{ $folder ? 'This folder is empty for now.' : 'HQ is preparing content for this category. Check back soon.' }}</div>@endif
    @endforelse</div>
    <div class="pagination-wrap">{{ $media->links() }}</div>
</x-layouts.agent>
