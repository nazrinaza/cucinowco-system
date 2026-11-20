@props([
    'title',
    'description',
    'updated' => '20 November 2026',
])

<x-layouts.public :title="$title.' | CuciNow.co'" :$description>
    <section class="legal-hero">
        <div class="site-shell legal-hero-inner">
            <div>
                <p class="eyebrow"><span></span> CuciNow legal</p>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
            </div>
            <div class="legal-meta">
                <span>Last updated</span>
                <strong>{{ $updated }}</strong>
                <small>{{ config('company.legal_name') }} &middot; Reg. {{ config('company.registration_number') }}</small>
            </div>
        </div>
    </section>

    <section class="legal-section">
        <div class="site-shell legal-layout">
            <aside class="legal-sidebar">
                <p>Legal documents</p>
                <nav aria-label="Legal documents">
                    <a href="{{ route('legal.privacy') }}" @class(['active' => request()->routeIs('legal.privacy')])>Privacy Policy</a>
                    <a href="{{ route('legal.terms') }}" @class(['active' => request()->routeIs('legal.terms')])>Terms of Use</a>
                    <a href="{{ route('legal.service-policy') }}" @class(['active' => request()->routeIs('legal.service-policy')])>Service Policy</a>
                </nav>
                <a class="legal-back" href="{{ route('home') }}">&larr; Back to CuciNow.co</a>
            </aside>

            <article class="legal-document">
                {{ $slot }}

                <div class="legal-contact">
                    <div><span>Questions?</span><strong>We are here to help.</strong></div>
                    <p>WhatsApp or call <a href="tel:+{{ config('company.phone') }}">+60 11-1242 8593</a>, or email <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</p>
                </div>
            </article>
        </div>
    </section>
</x-layouts.public>
