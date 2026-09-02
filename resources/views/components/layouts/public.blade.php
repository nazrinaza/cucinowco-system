<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CuciNow.co | Professional Cleaning by Thursina' }}</title>
    <meta name="description" content="Professional office, hall and specialist cleaning in Klang Valley. Request a clear estimate from CuciNow.co, backed by Thursina experience since 2000.">
    <meta name="theme-color" content="#f5b800">
    <meta property="og:title" content="CuciNow.co | Professional Cleaning by Thursina">
    <meta property="og:description" content="A cleaner space, right when you need it. Office, grand hall and specialist cleaning in Klang Valley.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:site_name" content="CuciNow.co">
    <meta property="og:locale" content="en_MY">
    <meta property="og:image" content="{{ asset('images/og-cucinow.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('images/og-cucinow.png') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="CuciNow.co — A cleaner space, right when you need it. Free site visit.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CuciNow.co | Professional Cleaning by Thursina">
    <meta name="twitter:description" content="Office, grand hall and specialist cleaning in Klang Valley, backed by Thursina experience since 2000.">
    <meta name="twitter:image" content="{{ asset('images/og-cucinow.png') }}">
    <meta name="twitter:image:alt" content="CuciNow.co — A cleaner space, right when you need it. Free site visit.">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                <a href="#services">Services</a>
                <a href="#how-it-works">How it works</a>
                <a href="#about">Why CuciNow</a>
                <a href="#faq">FAQ</a>
                <a href="#quote" class="button button-small">Free site visit</a>
            </nav>
        </div>
    </header>
    <main id="main">{{ $slot }}</main>
    <footer class="site-footer">
        <div class="site-shell footer-grid">
            <div>
                <img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co by Thursina" class="footer-logo">
                <p>Professional cleaning for workplaces, venues and shared spaces. Operated by Thursina Land & Services.</p>
            </div>
            <div><h2>Explore</h2><a href="#services">Services</a><a href="#quote">Request quote</a><a href="#about">About Thursina</a><a href="#faq">FAQ</a></div>
            <div><h2>Contact</h2><a href="tel:+{{ config('company.phone') }}">+60 11-5147 1145</a><a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a><p>{{ config('company.address') }}</p></div>
            <div><h2>Business</h2><p>Thursina Land & Services</p><p>Reg. {{ config('company.registration_number') }}</p><a href="{{ route('login') }}">Admin access</a></div>
        </div>
        <div class="site-shell footer-bottom"><span>&copy; {{ date('Y') }} CuciNow.co by Thursina.</span><span>Privacy &middot; Terms &middot; Service policy</span></div>
    </footer>
    <a class="whatsapp-float" href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I would like to book a free site visit for a cleaning service.') }}" target="_blank" rel="noopener">WhatsApp <span>Book a visit</span></a>
    @livewireScripts
</body>
</html>
