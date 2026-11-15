@php
    $fmt = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    $basisLabel = $basis === 'total' ? 'the invoice total' : 'net sales (invoice subtotal after discount, before tax)';
    $exampleSales = 3000;
    $exampleCommission = $rate > 0
        ? app(\App\Support\AgentCommissions::class)->commissionSen($exampleSales * 100, \App\Support\AgentCommissions::toSen($rate)) / 100
        : null;
    $faqs = [
        ['Who can join?', 'Anyone in Malaysia with good connections to offices, halls, hotels, factories, warehouses or event venues. Apply with your name, email and phone, verify your email, and HQ reviews your application. There is no fee to apply.'],
        ['Do I need to quote prices or manage the job?', 'No. You make the introduction. HQ handles the site assessment, quotation, scheduling and the cleaning itself, so you never have to negotiate or promise anything on our behalf.'],
        ['How is my referral tracked?', 'Your personal link and QR code carry your referral code. A valid referral is remembered for up to 90 days in the same browser, and every enquiry submitted keeps the source it came from. Clearing cookies or switching devices can prevent attribution.'],
        ['When do I earn commission?', 'When a customer you referred pays their invoice in full. A pending commission is recorded at the rate in effect that day, HQ reviews and approves it, then pays it out.'],
        ['How much can I earn?', $rate > 0
            ? 'The standard rate is '.$fmt($rate).'% of '.$basisLabel.'. HQ may agree a different rate with you, and your dashboard always shows the rate that applies to you.'
            : 'HQ sets your commission rate and confirms it when your application is approved. Your dashboard always shows the rate that applies to you.'],
        ['How do I get paid?', 'Add your bank account under My account. HQ transfers approved commission to that account and records a payout reference you can see on your dashboard. Amounts are paid to the exact sen.'],
        ['Can I earn by introducing other agents?', $introducerRate > 0
            ? 'Yes. When someone joins through your link and their referrals are paid, you earn an introducer reward of '.$fmt($introducerRate).'% on those invoices.'
            : 'You can invite other agents with your link. HQ may offer an introducer reward and will confirm the details with you.'],
        ['What can I share?', 'Use the HQ-approved visuals, videos and captions in Marketing tools. Captions include your link automatically. Please do not promise discounts, availability or results that HQ has not confirmed.'],
        ['Where can I follow my progress?', 'Your dashboard shows visits, enquiries, closed deals, paid invoices and your pending, approved and paid commission, all in one place.'],
    ];
@endphp
<x-layouts.agent title="How it works" :indexable="true">
    <div class="hiw">
        {{-- Hero --}}
        <section class="hiw-hero" aria-labelledby="hiw-title">
            <div class="hiw-hero-copy">
                <p class="agent-kicker">THE CUCINOW AGENT NETWORK</p>
                <h1 id="hiw-title">Good connections,<br><em>properly rewarded.</em></h1>
                <p class="hiw-lede">Know an office, hall or warehouse that needs professional cleaning? Introduce them to CuciNow and earn commission when their invoice is paid. We handle everything else.</p>
                <div class="hiw-hero-actions">
                    <a href="{{ route('agents.register') }}" class="agent-button">Apply to join →</a>
                    <nav class="hiw-jump" aria-label="On this page">
                        <a href="#how">How it works</a><a href="#why">Why join</a><a href="#faq">FAQs</a>
                    </nav>
                </div>
            </div>
            <div class="hiw-hero-media">
                <img src="{{ asset('images/hero-cleaning.webp') }}" alt="A CuciNow cleaner vacuuming an office at sunrise" width="1920" height="1079" fetchpriority="high">
                <div class="hiw-hero-badge"><strong>{{ $rate > 0 ? $fmt($rate).'%' : 'Paid' }}</strong><span>commission on paid invoices</span></div>
            </div>
        </section>

        {{-- Social proof --}}
        <section class="hiw-trust" aria-label="Organisations that have worked with CuciNow">
            <p>Trusted by teams at</p>
            <div class="hiw-logos">
                @foreach($clients as $client)
                    <img src="{{ asset('images/clients/small/'.rawurlencode($client).'.webp') }}" alt="{{ $client }}" width="360" height="203" loading="lazy">
                @endforeach
            </div>
        </section>

        {{-- How it works --}}
        <section class="hiw-section" id="how" aria-labelledby="how-title">
            <div class="hiw-section-head" data-reveal>
                <p class="agent-kicker">INTRODUCE · WE CLEAN · YOU EARN</p>
                <h2 id="how-title">Four steps from introduction to payout.</h2>
            </div>
            <ol class="hiw-steps">
                @foreach([
                    ['Apply', 'Create your agent account, choose your referral code and verify your email. HQ reviews every application.'],
                    ['Share', 'Send your personal link or branded QR code to the people who manage spaces: admins, facility managers, event organisers.'],
                    ['We clean', 'HQ visits the site, sends the quotation and delivers the cleaning. You can follow every lead from your dashboard.'],
                    ['You earn', 'Once the customer pays their invoice in full, your commission is recorded, approved and transferred to your bank.'],
                ] as $i => [$title, $text])
                    <li class="hiw-step" data-reveal style="--d: {{ $i * 90 }}ms">
                        <span class="hiw-step-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="hiw-link-card" data-reveal>
                <div>
                    <p class="agent-kicker">YOUR LINK WILL LOOK LIKE</p>
                    <code>{{ $exampleLink }}</code>
                </div>
                <p>Every agent gets a link and a matching QR code. Share it on WhatsApp, social media or print it.</p>
            </div>
        </section>

        {{-- Commission example --}}
        <section class="hiw-earn" aria-labelledby="earn-title" data-reveal>
            <div>
                <p class="agent-kicker">WHAT AN INTRODUCTION IS WORTH</p>
                <h2 id="earn-title">Simple, transparent commission.</h2>
                <p>Commission is calculated on {{ $basisLabel }} and recorded the day the invoice is paid in full. No targets, no fees, no chasing.</p>
            </div>
            @if($exampleCommission !== null)
                <dl class="hiw-calc" aria-label="Example commission">
                    <div><dt>Office deep clean{{ $basis === 'total' ? ' invoice' : ', net sales' }}</dt><dd>RM {{ number_format($exampleSales, 2) }}</dd></div>
                    <div><dt>Your rate</dt><dd>× {{ $fmt($rate) }}%</dd></div>
                    <div class="hiw-calc-total"><dt>You earn</dt><dd>RM {{ number_format($exampleCommission, 2) }}</dd></div>
                </dl>
            @else
                <div class="hiw-calc hiw-calc-pending"><strong>Rate confirmed on approval</strong><p>HQ sets your commission rate when your application is approved. You'll always see it on your dashboard.</p></div>
            @endif
        </section>

        {{-- Why join --}}
        <section class="hiw-section" id="why" aria-labelledby="why-title">
            <div class="hiw-section-head" data-reveal>
                <p class="agent-kicker">WHY JOIN</p>
                <h2 id="why-title">Everything you need to refer with confidence.</h2>
            </div>
            <div class="hiw-benefits">
                @foreach([
                    ['RM', 'Earn on every paid job', 'Commission on each invoice your referral pays in full, transferred to your own bank account.'],
                    ['QR', 'Ready-to-share content', 'Branded QR code, visuals, videos and captions from HQ, with your link already included.'],
                    ['↗', 'See every lead move', 'Follow visits, enquiries, quotations and payments in your dashboard, in real time.'],
                    ['✓', 'We do the hard part', 'Site visits, quotations and the cleaning itself are handled by the CuciNow team.'],
                ] as $i => [$icon, $title, $text])
                    <article class="hiw-benefit" data-reveal style="--d: {{ $i * 80 }}ms">
                        <span aria-hidden="true">{{ $icon }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
            <div class="hiw-audience" data-reveal>
                <p>Great agents are often</p>
                <ul>
                    @foreach(['Office & admin managers', 'Property & facility managers', 'Event organisers', 'Contractors & renovators', 'Real estate agents', 'Business owners'] as $who)
                        <li>{{ $who }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- FAQ --}}
        <section class="hiw-section hiw-faq" id="faq" aria-labelledby="faq-title">
            <div class="hiw-section-head" data-reveal>
                <p class="agent-kicker">FAQS</p>
                <h2 id="faq-title">Questions, answered.</h2>
                <p>Still unsure? <a href="mailto:agents@cucinow.co">Email HQ</a> or read the full <a href="{{ route('agents.guide') }}">programme guide</a>.</p>
            </div>
            <div class="hiw-faq-list">
                @foreach($faqs as $i => [$q, $a])
                    <details name="hiw-faq" @if($i === 0) open @endif>
                        <summary>{{ $q }}</summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- Final call to action --}}
        <section class="hiw-cta" aria-labelledby="cta-title" data-reveal>
            <p class="agent-kicker">JOIN THE NETWORK</p>
            <h2 id="cta-title">Your network. Our expertise.<br><em>New possibilities.</em></h2>
            <p>Apply in two minutes. Get your link once HQ approves you.</p>
            <div class="hiw-hero-actions">
                @auth('agent')
                    <a href="{{ route('agents.dashboard') }}" class="agent-button">Go to my dashboard →</a>
                @else
                    <a href="{{ route('agents.register') }}" class="agent-button">Apply to join →</a>
                    <a href="{{ route('agents.login') }}" class="agent-button agent-button-ghost">Agent sign in</a>
                @endauth
            </div>
        </section>
    </div>
</x-layouts.agent>
