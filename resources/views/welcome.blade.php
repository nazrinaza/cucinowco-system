<x-layouts.public>
    <section class="hero">
        <div class="site-shell hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span></span> Cleaning, handled properly</p>
                <h1>A cleaner space,<br><em>right when you need it.</em></h1>
                <p class="hero-ms">A cleaner space, right on time.</p>
                <p class="hero-lead">Professional office, grand hall and specialist cleaning across Klang Valley, with clear scope, dependable coordination and experience built by Thursina since 2000.</p>
                <div class="hero-actions">
                    <a href="#quote" class="button">Book a cleaning <small>Request an estimate</small></a>
                    <a href="https://wa.me/{{ config('company.whatsapp') }}" class="text-link">Talk to our team <span>&rarr;</span></a>
                </div>
                <div class="hero-proof">
                    <div><strong>2000</strong><span>Thursina established</span></div>
                    <div><strong>2 sectors</strong><span>Government & private experience</span></div>
                    <div><strong>1 team</strong><span>From quote to handover</span></div>
                </div>
            </div>
            <div class="hero-visual" aria-label="CuciNow service promise">
                <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
                <div class="visual-card visual-card-main"><span class="visual-number">01</span><p>Tell us the space</p><strong>Office<br>Grand hall</strong></div>
                <div class="visual-card visual-card-side"><span>Fast response</span><strong>Clear quote.<br>No guesswork.</strong></div>
                <div class="visual-seal"><span>by</span><strong>Thursina</strong><small>Since 2000</small></div>
                <div class="spark spark-one"></div><div class="spark spark-two"></div><div class="spark spark-three"></div>
            </div>
        </div>
    </section>

    <section class="trust-strip" aria-label="Service principles"><div class="site-shell"><span>Trained operations</span><span>Site-appropriate equipment</span><span>Scope confirmed upfront</span><span>Zero-defect mindset</span></div></section>

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
                    <article class="service-card"><span class="service-no">{{ $number }}</span><div><p>{{ $ms }}</p><h3>{{ $name }}</h3><p>{{ $description }}</p><a href="#quote">Request this service <span>&rarr;</span></a></div></article>
                @endforeach
            </div>
            <p class="source-note">Thursina profile source: carpet cleaning, disinfection, building/contract cleaning, initial cleaning, high-rise cleaning, floor coating and polishing, landscaping and furniture supply. Grand hall packages are a CuciNow launch service.</p>
        </div>
    </section>

    <section id="how-it-works" class="dark-section section-pad">
        <div class="site-shell"><div class="section-heading light"><div><p class="eyebrow"><span></span> Simple by design</p><h2>From “need cleaning”<br>to ready-to-use.</h2></div><p>No long back-and-forth. Share the essentials and our team coordinates the rest.</p></div>
            <div class="process-grid">
                <article><span>01</span><h3>Choose your service</h3><p>Select the space, size and preferred cleaning schedule.</p></article>
                <article><span>02</span><h3>Receive an estimate</h3><p>See a preliminary RM estimate, then submit your details for confirmation.</p></article>
                <article><span>03</span><h3>Confirm scope & slot</h3><p>We verify access, site condition, inclusions and the available team.</p></article>
                <article><span>04</span><h3>Clean & hand over</h3><p>Our operations team completes the agreed scope and closes the job clearly.</p></article>
            </div>
        </div>
    </section>

    <section id="about" class="section-pad about-section">
        <div class="site-shell about-grid">
            <div class="about-panel"><p class="eyebrow"><span></span> Built on experience</p><h2>A new booking experience.<br>A proven operating base.</h2><p>CuciNow.co is the customer-facing cleaning platform of Thursina Land & Services, established on 17 February 2000. Thursina's company profile records experience in building maintenance, specialist machinery and service delivery for both government and private-sector projects.</p><p>The management team brings backgrounds in operations, business management, corporate communications, sales and intensive cleaning and building-maintenance training. CuciNow turns that operational experience into a clearer, mobile-first way to request, schedule and manage cleaning services.</p><a href="#quote" class="text-link">Start your request <span>&rarr;</span></a></div>
            <div class="principle-list">
                <article><span>01</span><div><h3>Clear scope</h3><p>We confirm what is included, the working area and practical site conditions before final pricing.</p></div></article>
                <article><span>02</span><div><h3>Fit-for-site methods</h3><p>Equipment, chemicals and manpower are matched to the service instead of using one approach everywhere.</p></div></article>
                <article><span>03</span><div><h3>Quality feedback loop</h3><p>Thursina's stated commitment is to use client feedback to improve future service and uphold a zero-defect mindset.</p></div></article>
                <article><span>04</span><div><h3>Budget-aware delivery</h3><p>Scope decisions balance the expected result, safety, timing and agreed quotation.</p></div></article>
            </div>
        </div>
    </section>

    <section id="quote" class="quote-section section-pad"><div class="site-shell quote-grid"><div class="quote-intro"><p class="eyebrow"><span></span> Instant estimate</p><h2>Tell us what needs cleaning.</h2><p>Get a preliminary estimate now. Our team will confirm the final price after checking the site details and service scope.</p><ul><li>Prices shown in RM</li><li>No payment required to submit</li><li>Service slot confirmed separately</li></ul></div><livewire:quote-estimator /></div></section>

    <section class="experience-section section-pad"><div class="site-shell"><p class="eyebrow"><span></span> Thursina profile experience</p><h2>Work across complex,<br>high-expectation environments.</h2><p class="experience-lead">The supplied company profile records client and project experience associated with organisations and sites including:</p><div class="experience-list"><span>Maybank</span><span>CIMB</span><span>Tenaga Nasional</span><span>University of Malaya</span><span>UiTM</span><span>JKR</span><span>MRT Corp</span><span>Perodua</span><span>Proton</span><span>J&amp;T Express</span><span>Firefly</span><span>NSG Group</span></div><p class="source-note">Names reflect the historical company profile and do not imply current contracts or endorsements.</p></div></section>

    <section id="faq" class="section-pad faq-section"><div class="site-shell faq-grid"><div><p class="eyebrow"><span></span> FAQ</p><h2>Before you book.</h2><p>Still unsure? Message us on WhatsApp and describe the space.</p></div><div class="accordion-list">
        @foreach ([
            ['Which areas do you cover?','Our launch focus is Klang Valley, starting from our Sungai Buloh operating base. Larger or outstation projects can be reviewed case by case.'],
            ['Is the instant estimate the final price?','No. It is a planning estimate based on service and size. We confirm final pricing after reviewing condition, access, inclusions and timing.'],
            ['Do you provide cleaning equipment and chemicals?','Yes, the required method, equipment and materials are confirmed for each scope. Tell us about sensitive surfaces, pets or site restrictions.'],
            ['Can I arrange recurring office cleaning?','Yes. Select weekly, fortnightly or monthly in the quote form, or ask for a custom contract schedule.'],
            ['Can you clean before or after an event?','Yes. CuciNow offers pre-event preparation and post-event hall or venue cleaning, subject to access and timing.'],
            ['How early should I book?','Two to five days ahead is recommended. Urgent requests are reviewed based on team availability.'],
            ['How do payments work?','Your confirmed quotation or invoice will show the accepted methods and due date. Online FPX, e-wallet/card, bank transfer and cash can be enabled by the business.'],
            ['Is SST included?','SST is shown only when legally applicable and the business is registered to charge it. Any applicable tax appears clearly on the final invoice.'],
        ] as [$question,$answer])<details><summary>{{ $question }}<span>+</span></summary><p>{{ $answer }}</p></details>@endforeach
    </div></div></section>

    <section class="final-cta"><div class="site-shell final-cta-inner"><div><p>Ready when your space is.</p><h2>Clean. Organised. Ready.</h2></div><a href="#quote" class="button button-dark">Get my estimate <small>Request a quote</small></a></div></section>

    <section class="newsletter"><div class="site-shell newsletter-inner"><div><h2>Useful cleaning notes. No clutter.</h2><p>Occasional service reminders, practical tips and CuciNow updates.</p></div><form action="{{ route('newsletter.store') }}" method="post">@csrf<input name="email" type="email" required placeholder="Your email address" aria-label="Email address"><button type="submit">Subscribe</button></form>@if(session('newsletter_success'))<p class="form-success">{{ session('newsletter_success') }}</p>@endif</div></section>
</x-layouts.public>
