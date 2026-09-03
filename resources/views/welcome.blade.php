<x-layouts.public>
    <section class="hero">
        <div class="site-shell hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span></span> Cleaning, handled properly</p>
                <h1>A cleaner space,<br><em>right when you need it.</em></h1>
                <p class="hero-ms">A cleaner space, right on time.</p>
                <p class="hero-lead">Professional office, grand hall and specialist cleaning across Klang Valley, with clear scope, dependable coordination and experience built by Thursina since 2000.</p>
                <div class="hero-actions">
                    <a href="#site-visit" class="button">Book my free site visit <small>No obligation &middot; Clear quotation</small></a>
                    <a href="https://wa.me/{{ config('company.whatsapp') }}" class="text-link">Talk to our team <span>&rarr;</span></a>
                </div>
                <div class="hero-proof">
                    <div><strong>2000</strong><span>Thursina established</span></div>
                    <div><strong>2 sectors</strong><span>Government & private experience</span></div>
                    <div><strong>6 services</strong><span>Commercial &amp; specialist cleaning</span></div>
                </div>
            </div>
            <div id="site-visit" class="hero-form-column" aria-label="Book a free CuciNow site visit">
                <div class="hero-form-orbit orbit-one"></div><div class="hero-form-orbit orbit-two"></div>
                <div class="hero-form-badge"><span>Free</span> site assessment</div>
                <livewire:site-visit-form />
            </div>
        </div>
    </section>

    <section class="trust-strip" aria-label="Service principles"><div class="site-shell"><span>Trained operations</span><span>Professional High-Powered Machinery</span><span>Scope confirmed upfront</span><span>Zero-defect mindset</span></div></section>

    <section id="services" class="section-pad">
        <div class="site-shell">
            <div class="section-heading"><div><p class="eyebrow"><span></span> Services</p><h2>The right clean for<br>the space you run.</h2></div><p>Start with the service that best fits. We confirm size, condition, access and timing before the job.</p></div>
            <div class="service-grid">
                @foreach ([
                    ['01','Office Cleaning','Workplace','Reliable workplace cleaning, from individual visits to managed service schedules.'],
                    ['02','Grand Hall & Event','Venue & Event','Pre-event preparation and post-event reset for halls, venues and shared spaces.'],
                    ['03','Carpet Cleaning','Specialist Cleaning','Machine shampoo cleaning for carpets and selected soft furnishings.'],
                    ['04','Deep & Initial Clean','Detailed Cleaning','Detailed cleaning before occupancy, after renovation or for a full space reset.'],
                    ['05','Specialist Care','Specialist Services','Disinfection and floor polishing delivered to a confirmed scope.'],
                ] as [$number,$name,$ms,$description])
                    <article class="service-card"><span class="service-no">{{ $number }}</span><div><p>{{ $ms }}</p><h3>{{ $name }}</h3><p>{{ $description }}</p><a href="#site-visit">Book a free site visit <span>&rarr;</span></a></div></article>
                @endforeach
            </div>
            <p class="source-note">Thursina profile source: carpet cleaning, disinfection, building/contract cleaning, initial cleaning, high-rise cleaning, floor coating and polishing, landscaping and furniture supply. Grand hall packages are a CuciNow launch service.</p>
        </div>
    </section>

    @php($spaceTypes = [
        ['01','Corporate Office','Workstations, meeting rooms and shared facilities.'],
        ['02','Grand Hall','Large-capacity spaces that need planned teams and machinery.'],
        ['03','Hotel / Ballroom','Guest-facing spaces where timing and presentation matter.'],
        ['04','Event Place','Pre-event preparation or a thorough post-event reset.'],
        ['05','Factory / Warehouse','Operational areas with access, safety and surface considerations.'],
        ['06','Universities','Academic, administrative and shared campus spaces.'],
    ])
    @php($cleanTypes = [
        ['01','Deep Clean','A detailed reset for built-up dust, grime and overlooked areas.'],
        ['02','Tiles Cleaning','Targeted care for tiled surfaces, grout lines and embedded dirt.'],
        ['03','Window Cleaning','Clearer glass and frames, planned around access and working height.'],
        ['04','General Cleaning','Practical upkeep for everyday dust, surfaces and high-use areas.'],
        ['05','Disinfect and Fragrance','Hygiene-focused treatment followed by a clean, fresh finish.'],
        ['06','Carpet Cleaning','Machine-assisted care for carpet fibres, embedded dirt and visible wear.'],
    ])
    <section id="scope-guide" class="scope-guide section-pad">
        <div class="site-shell">
            <div class="section-heading scope-heading">
                <div><p class="eyebrow"><span></span> Space &amp; clean guide</p><h2>Different spaces.<br>Different cleaning demands.</h2></div>
                <p>Start with where the work happens, then identify the result you need. We use both to recommend the right method, machinery, manpower and timing.</p>
            </div>
            <div class="scope-builder">
                <section class="scope-panel space-panel" aria-labelledby="space-type-heading">
                    <header class="scope-panel-head"><div><span>01</span><p>Choose the environment</p></div><h3 id="space-type-heading">Space Type</h3></header>
                    <div class="space-type-grid">
                        @foreach($spaceTypes as [$number,$name,$description])
                            <article class="space-type-card"><span>{{ $number }}</span><h4>{{ $name }}</h4><p>{{ $description }}</p></article>
                        @endforeach
                    </div>
                </section>
                <section class="scope-panel clean-panel" aria-labelledby="clean-type-heading">
                    <header class="scope-panel-head"><div><span>02</span><p>Define the result</p></div><h3 id="clean-type-heading">Clean Type</h3></header>
                    <div class="clean-type-list">
                        @foreach($cleanTypes as [$number,$name,$description])
                            <article><span>{{ $number }}</span><div><h4>{{ $name }}</h4><p>{{ $description }}</p></div></article>
                        @endforeach
                    </div>
                </section>
            </div>
            <div class="scope-outcome">
                <div class="scope-formula" aria-label="Space type plus clean type equals a site-specific scope"><span>Space type</span><b>+</b><span>Clean type</span><b>=</b><strong>Site-specific scope</strong></div>
                <a href="#site-visit" class="button button-dark">Find the right clean <small>Free site visit</small></a>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="dark-section section-pad">
        <div class="site-shell"><div class="section-heading light"><div><p class="eyebrow"><span></span> Simple by design</p><h2>From “need cleaning”<br>to ready-to-use.</h2></div><p>No long back-and-forth. Share the essentials and our team coordinates the rest.</p></div>
            <div class="process-grid">
                <article><span>01</span><h3>Book a free visit</h3><p>Share the service, site address and your preferred assessment time.</p></article>
                <article><span>02</span><h3>We assess the site</h3><p>Our team reviews access, condition, measurements and the required method.</p></article>
                <article><span>03</span><h3>Receive a clear quote</h3><p>We prepare the service scope, timing and pricing for your approval.</p></article>
                <article><span>04</span><h3>Clean & hand over</h3><p>Our operations team completes the agreed scope and closes the job clearly.</p></article>
            </div>
        </div>
    </section>

    <section id="about" class="section-pad about-section">
        <div class="site-shell about-grid">
            <div class="about-panel"><p class="eyebrow"><span></span> Built on experience</p><h2>A new booking experience.<br>A proven operating base.</h2><p>CuciNow.co is the customer-facing cleaning platform of Thursina Land & Services, established on 17 February 2000. Thursina's company profile records experience in building maintenance, specialist machinery and service delivery for both government and private-sector projects.</p><p>The management team brings backgrounds in operations, business management, corporate communications, sales and intensive cleaning and building-maintenance training. CuciNow turns that operational experience into a clearer, mobile-first way to request, schedule and manage cleaning services.</p><a href="#site-visit" class="text-link">Book a free site visit <span>&rarr;</span></a></div>
            <div class="principle-list">
                <article><span>01</span><div><h3>Clear scope</h3><p>We confirm what is included, the working area and practical site conditions before final pricing.</p></div></article>
                <article><span>02</span><div><h3>Fit-for-site methods</h3><p>Equipment, chemicals and manpower are matched to the service instead of using one approach everywhere.</p></div></article>
                <article><span>03</span><div><h3>Quality feedback loop</h3><p>Thursina's stated commitment is to use client feedback to improve future service and uphold a zero-defect mindset.</p></div></article>
                <article><span>04</span><div><h3>Budget-aware delivery</h3><p>Scope decisions balance the expected result, safety, timing and agreed quotation.</p></div></article>
            </div>
        </div>
    </section>

    @php($clients = [['Maybank.png','Maybank'],['CIMB.png','CIMB'],['Tenaga Nasional.png','Tenaga Nasional'],['University of Malaya.png','University of Malaya'],['UiTM.png','UiTM'],['JKR.png','JKR'],['MRT.png','MRT Corp'],['Perodua.png','Perodua'],['Proton.png','Proton'],['J&T Express.png','J&T Express'],['Firefly.png','Firefly'],['NSG Group.png','NSG Group']])
    <section class="experience-section section-pad">
        <div class="site-shell">
            <p class="eyebrow"><span></span> Thursina profile experience</p>
            <h2>Work across complex,<br>high-expectation environments.</h2>
            <p class="experience-lead">The supplied company profile records client and project experience associated with organisations and sites including:</p>
        </div>
        <div class="experience-marquee" aria-label="Selected organisations from Thursina's historical company profile">
            <div class="experience-track">
                @foreach([false,true] as $duplicate)
                    <div class="experience-logo-set" @if($duplicate) aria-hidden="true" @endif>
                        @foreach($clients as [$file,$client])
                            <figure class="experience-logo"><img src="{{ asset('images/clients/'.$file) }}" alt="{{ $duplicate ? '' : $client.' logo' }}" decoding="async"></figure>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="site-shell"><p class="source-note">Logos reflect historical company-profile experience and do not imply current contracts or endorsements.</p></div>
    </section>

    <section id="faq" class="section-pad faq-section"><div class="site-shell faq-grid"><div><p class="eyebrow"><span></span> FAQ</p><h2>Before you book.</h2><p>Still unsure? Message us on WhatsApp and describe the space.</p></div><div class="accordion-list" data-accordion>
        @foreach ([
            ['Which areas do you cover?','Our launch focus is Klang Valley, starting from our Sungai Buloh operating base. Larger or outstation projects can be reviewed case by case.'],
            ['Is the site visit really free?','Yes. There is no charge and no obligation for a confirmed site assessment within our service coverage. We use the visit to understand access, condition and the correct cleaning scope.'],
            ['Do you provide cleaning equipment and chemicals?','Yes, the required method, equipment and materials are confirmed for each scope. Tell us about sensitive surfaces, pets or site restrictions.'],
            ['Can I arrange recurring office cleaning?','Yes. Select weekly, fortnightly or monthly in the quote form, or ask for a custom contract schedule.'],
            ['Can you clean before or after an event?','Yes. CuciNow offers pre-event preparation and post-event hall or venue cleaning, subject to access and timing.'],
            ['How early should I book?','Two to five days ahead is recommended. Urgent requests are reviewed based on team availability.'],
            ['How do payments work?','Your confirmed quotation or invoice will show the accepted methods and due date. Online FPX, e-wallet/card, bank transfer and cash can be enabled by the business.'],
            ['Is SST included?','SST is shown only when legally applicable and the business is registered to charge it. Any applicable tax appears clearly on the final invoice.'],
        ] as [$question,$answer])<details name="cucinow-faq"><summary>{{ $question }}<span>+</span></summary><p>{{ $answer }}</p></details>@endforeach
    </div></div></section>

    <section class="final-cta"><div class="site-shell final-cta-inner"><div><p>Ready when your space is.</p><h2>Clean. Organised. Ready.</h2></div><a href="#site-visit" class="button button-dark">Book my free site visit <small>No obligation &middot; Clear quotation</small></a></div></section>

    <section class="newsletter"><div class="site-shell newsletter-inner"><div><h2>Useful cleaning notes. No clutter.</h2><p>Occasional service reminders, practical tips and CuciNow updates.</p></div><form action="{{ route('newsletter.store') }}" method="post">@csrf<input name="email" type="email" required placeholder="Your email address" aria-label="Email address"><button type="submit">Subscribe</button></form>@if(session('newsletter_success'))<p class="form-success">{{ session('newsletter_success') }}</p>@endif</div></section>
</x-layouts.public>
