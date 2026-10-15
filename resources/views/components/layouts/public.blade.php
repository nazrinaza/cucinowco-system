@props([
    'title' => 'Commercial Cleaning Services Klang Valley | CuciNow.co',
    'description' => 'Professional office, hall, hotel, warehouse and event-space cleaning across Klang Valley. Book a free site assessment with CuciNow.co.',
    'canonical' => null,
    'image' => null,
    'robots' => 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
    'structuredData' => null,
    'preloadImage' => null,
])
@php
    $canonicalUrl = $canonical ?: url()->current();
    $socialImage = $image ?: asset('images/og-cucinow.png');
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="theme-color" content="#f5b800">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="CuciNow.co">
    <meta property="og:locale" content="en_MY">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:secure_url" content="{{ $socialImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="CuciNow.co — A cleaner space, right when you need it. Free site visit.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <meta name="twitter:image:alt" content="CuciNow.co — A cleaner space, right when you need it. Free site visit.">
    @if($preloadImage)<link rel="preload" as="image" href="{{ $preloadImage }}" type="image/webp" fetchpriority="high">@endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @if($structuredData)
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
    <x-google-tag area="public" />
    @vite(['resources/css/app.css', 'resources/js/public.js'])
    @livewireStyles
</head>
<body class="bg-cream text-ink antialiased">
    <a href="#main" class="skip-link">Skip to content</a>
    <header class="site-header" data-header>
        <div class="site-shell header-inner">
            <a href="{{ route('home') }}" aria-label="CuciNow.co home" class="brand-link">
                <img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co by Thursina" class="brand-logo">
            </a>
            <button class="menu-button" type="button" aria-expanded="false" aria-controls="primary-navigation" data-menu-button>
                <span></span><span></span><span></span><span class="sr-only">Open navigation</span>
            </button>
            <nav id="primary-navigation" class="primary-nav" aria-label="Primary navigation" data-menu>
                <a href="{{ route('home') }}#scope-guide">Space &amp; clean guide</a>
                <a href="{{ route('home') }}#how-it-works">How it works</a>
                <a href="{{ route('home') }}#about">Why CuciNow</a>
                <a href="{{ route('home') }}#faq">FAQ</a>
                <a href="{{ route('home') }}#site-visit" class="button button-small">Free site visit</a>
            </nav>
        </div>
    </header>
    <main id="main">{{ $slot }}</main>
    <footer class="site-footer">
        <div class="site-shell footer-grid">
            <div>
                <img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co by Thursina" class="footer-logo">
                <p>Professional cleaning for workplaces, venues and shared spaces. Operated by {{ config('company.legal_name') }}.</p>
            </div>
            <div><h2>Explore</h2><a href="{{ route('home') }}#scope-guide">Space &amp; clean guide</a><a href="{{ route('home') }}#site-visit">Book free site visit</a><a href="{{ route('home') }}#about">About Thursina</a><a href="{{ route('home') }}#faq">FAQ</a></div>
            <div><h2>Contact</h2><a href="tel:+{{ config('company.phone') }}">+60 11-1242 8593</a><a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a><p>{{ config('company.address') }}&nbsp;<span class="contact-flag" role="img" aria-label="Malaysia flag">🇲🇾</span></p></div>
            <div><h2>Business</h2><p>{{ config('company.legal_name') }}</p><p>Reg. {{ config('company.registration_number') }}</p></div>
        </div>
        <div class="site-shell footer-bottom"><span>&copy; {{ date('Y') }} CuciNow.co by Thursina.</span><span>Privacy &middot; Terms &middot; Service policy</span></div>
    </footer>
    <a class="whatsapp-float" href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I would like to book a free site visit for a cleaning service.') }}" target="_blank" rel="noopener">WhatsApp <span>Book a visit</span></a>
    @livewireScripts
</body>
</html>
