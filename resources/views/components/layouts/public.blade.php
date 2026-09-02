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
    <link rel="icon" href="{{ asset('favicon.ico') }}">
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
                <a href="#quote" class="button button-small">Get a quote</a>
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
    <a class="whatsapp-float" href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I would like to ask about a cleaning service.') }}" target="_blank" rel="noopener">WhatsApp <span>Chat now</span></a>
    @livewireScripts
</body>
</html>
