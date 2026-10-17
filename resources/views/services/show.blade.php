@php
    $canonical = route('services.show', $service);
    $title = $details['name'].' Klang Valley | CuciNow.co';
    $description = $details['short_description'].' Book a free site assessment across Klang Valley.';
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Service',
                '@id' => $canonical.'#service',
                'name' => $details['name'],
                'description' => $details['short_description'],
                'url' => $canonical,
                'provider' => ['@id' => route('home').'#business'],
                'areaServed' => [
                    ['@type' => 'AdministrativeArea', 'name' => 'Klang Valley'],
                    ['@type' => 'State', 'name' => 'Selangor'],
                    ['@type' => 'City', 'name' => 'Kuala Lumpur'],
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $details['name'], 'item' => $canonical],
                ],
            ],
        ],
    ];
@endphp

<x-layouts.public :$title :$description :$canonical :structured-data="$schema">
    <section class="service-hero section-pad" style="--service-hero-image: url('{{ asset($details['hero_image']) }}')">
        <div class="site-shell service-hero-grid">
            <div>
                <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><span>{{ $details['name'] }}</span></nav>
                <p class="eyebrow"><span></span> Commercial cleaning &middot; Klang Valley</p>
                <h1>{{ $details['headline'] }}</h1>
                <p class="service-intro">{{ $details['intro'] }}</p>
                <div class="hero-actions">
                    <a href="{{ route('home') }}#site-visit" class="button">Book my free site visit <small>No obligation &middot; Clear quotation</small></a>
                    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I would like to ask about '.$details['name'].'.') }}" class="text-link" target="_blank" rel="noopener">Ask on WhatsApp <span>&rarr;</span></a>
                </div>
            </div>
            <aside class="service-summary">
                <span>Service assessment</span>
                <h2>Scope before price.</h2>
                <p>We inspect access, condition, measurements and the result you need before confirming the method, team and quotation.</p>
            </aside>
        </div>
    </section>

    <section class="section-pad service-details">
        <div class="site-shell service-detail-grid">
            <article>
                <p class="eyebrow"><span></span> Suitable spaces</p>
                <h2>Where this service fits.</h2>
                <ul class="service-check-list">@foreach($details['spaces'] as $space)<li>{{ $space }}</li>@endforeach</ul>
            </article>
            <article>
                <p class="eyebrow"><span></span> Scope options</p>
                <h2>What we can assess.</h2>
                <ul class="service-check-list">@foreach($details['includes'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </article>
        </div>
    </section>

    <section class="dark-section section-pad">
        <div class="site-shell">
            <div class="section-heading light"><div><p class="eyebrow"><span></span> Clear process</p><h2>From assessment<br>to handover.</h2></div><p>The final method and price depend on what our team confirms at your site.</p></div>
            <div class="process-grid">
                <article><span>01</span><h3>Request a visit</h3><p>Share the space, clean type and preferred assessment time.</p></article>
                <article><span>02</span><h3>Assess the site</h3><p>We review access, measurements, condition and practical restrictions.</p></article>
                <article><span>03</span><h3>Approve the scope</h3><p>You receive a clear quotation describing the work, timing and price.</p></article>
                <article><span>04</span><h3>Clean &amp; hand over</h3><p>Our operations team completes the agreed scope and records the job.</p></article>
            </div>
        </div>
    </section>

    <section class="section-pad related-services">
        <div class="site-shell">
            <div class="section-heading"><div><p class="eyebrow"><span></span> Related services</p><h2>Build the right scope<br>for your space.</h2></div></div>
            <div class="related-service-grid">
                @foreach($related as $slug => $item)
                    <a href="{{ route('services.show', $slug) }}"><span>Explore service</span><h3>{{ $item['name'] }}</h3><p>{{ $item['short_description'] }}</p><b aria-hidden="true">&rarr;</b></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="final-cta"><div class="site-shell final-cta-inner"><div><p>Need {{ strtolower($details['name']) }}?</p><h2>Let’s assess it properly.</h2></div><a href="{{ route('home') }}#site-visit" class="button button-dark">Book my free site visit <small>Klang Valley &middot; No obligation</small></a></div></section>
</x-layouts.public>
