@props([
    'text' => '',           // caption or link to share (may already contain the referral link)
    'textFrom' => null,     // CSS selector of an input whose value is shared instead (link generator)
    'refUrl' => null,       // the agent's referral link; tagged per network with ?via=
    'title' => 'CuciNow',
    'imageUrl' => null,     // signed public image for Pinterest / Copy as HTML
    'file' => null,         // private download URL for the phone share sheet
    'fileName' => null,
    'fileType' => null,
])
@php($menuId = 'share-'.\Illuminate\Support\Str::random(8))
<div class="mt-share" data-share-menu
     data-text="{{ $text }}" @if($textFrom) data-text-from="{{ $textFrom }}" @endif
     data-ref-url="{{ $refUrl }}" data-title="{{ $title }}"
     @if($imageUrl) data-image-url="{{ $imageUrl }}" @endif
     @if($file) data-file="{{ $file }}" data-file-name="{{ $fileName }}" data-file-type="{{ $fileType }}" @endif>
    <button type="button" class="agent-button agent-button-light mt-share-toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="{{ $menuId }}">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
        Share
    </button>
    <div class="mt-share-menu" id="{{ $menuId }}" role="menu" hidden>
        <button type="button" role="menuitem" data-share-action="copy-html">Copy as HTML</button>
        <button type="button" role="menuitem" data-share-action="copy-text">Copy text</button>
        @if($imageUrl)<button type="button" role="menuitem" data-share-action="copy-image">Copy image</button>@endif
        <p class="mt-share-label">SHARE</p>
        <button type="button" role="menuitem" data-share-action="whatsapp"><span class="mt-net mt-net-wa" aria-hidden="true"></span>WhatsApp</button>
        <button type="button" role="menuitem" data-share-action="facebook"><span class="mt-net mt-net-fb" aria-hidden="true"></span>Facebook</button>
        <button type="button" role="menuitem" data-share-action="threads"><span class="mt-net mt-net-th" aria-hidden="true"></span>Threads</button>
        <button type="button" role="menuitem" data-share-action="x"><span class="mt-net mt-net-x" aria-hidden="true"></span>X (formerly Twitter)</button>
        @if($imageUrl)<button type="button" role="menuitem" data-share-action="pinterest"><span class="mt-net mt-net-pin" aria-hidden="true"></span>Pinterest</button>@endif
        <button type="button" role="menuitem" data-share-action="native" hidden><span class="mt-net mt-net-more" aria-hidden="true"></span>More on this phone…</button>
        <p class="mt-share-status" aria-live="polite"></p>
    </div>
</div>
