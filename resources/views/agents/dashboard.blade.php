<x-layouts.agent title="Your overview">
    <section class="agent-welcome"><div><p class="agent-kicker">YOUR PARTNER WORKSPACE</p><h1>Let’s make connections,<br>{{ str($agent->name)->before(' ') }}.</h1><p>Share with confidence. See where every referral goes.</p></div><span class="agent-status">● Approved agent</span></section>
    <section class="agent-metrics" aria-label="Lifetime performance">
        @foreach(['visits' => 'Referral visits', 'leads' => 'Leads received', 'closed' => 'Closed deals', 'completed' => 'Jobs completed', 'paid' => 'Paid invoices'] as $key => $label)<article><span>{{ $label }}</span><strong>{{ number_format($summary[$key]) }}</strong></article>@endforeach
    </section>
    <section class="agent-card agent-earnings" aria-label="Your commission earnings">
        <div class="agent-earnings-head">
            <div><p class="agent-kicker">YOUR EARNINGS</p><h2>RM {{ number_format((float) $earnings['lifetime'], 2) }} <small>lifetime</small></h2></div>
            <span class="agent-rate">{{ (float) $rate > 0 ? rtrim(rtrim($rate, '0'), '.').'% commission rate' : 'Rate to be confirmed by HQ' }}</span>
        </div>
        <div class="agent-earnings-grid">
            <p><span>Pending HQ review</span><strong>RM {{ number_format((float) $earnings['pending'], 2) }}</strong></p>
            <p><span>Approved for payout</span><strong>RM {{ number_format((float) $earnings['approved'], 2) }}</strong></p>
            <p><span>Paid to you</span><strong>RM {{ number_format((float) $earnings['paid'], 2) }}</strong></p>
        </div>
        @if(! $agent->hasPayoutDetails())
            <p class="agent-payout-prompt">Add your bank account so HQ can pay your commission. <a href="{{ route('agents.account') }}#payout">Add bank details →</a></p>
        @else
            <p class="agent-payout-prompt">Payouts go to {{ $agent->payoutBankName() }} {{ $agent->maskedPayoutAccountNumber() }}. <a href="{{ route('agents.account') }}#payout">Change</a></p>
        @endif
        @php($programme = app(\App\Support\ProgrammeSettings::class))
        <p class="agent-payout-schedule">Next payout <strong>{{ $programme->nextPayoutDate()->format('d M Y') }}</strong> · paid on the {{ collect($programme->payoutDays())->map(fn ($d) => $d.(in_array($d % 100, [11, 12, 13]) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$d % 10] ?? 'th')))->join(', ', ' and ') }} of each month · minimum RM {{ number_format((float) $programme->payoutMinimum(), 2) }}@if((float) $programme->payoutMinimum() > 0 && (float) $earnings['approved'] > 0 && (float) $earnings['approved'] < (float) $programme->payoutMinimum()) · RM {{ number_format((float) $programme->payoutMinimum() - (float) $earnings['approved'], 2) }} more approved to reach it @endif</p>
        <div class="agent-funnel" aria-label="Conversion funnel">
            @foreach([['Visit → lead', $summary['visit_to_lead']], ['Lead → closed deal', $summary['conversion']], ['Closed → paid', $summary['closed_to_paid']]] as [$label, $percent])
                <div><p><span>{{ $label }}</span><strong>{{ $percent }}%</strong></p><i style="--w:{{ min(100, $percent) }}%"></i></div>
            @endforeach
        </div>
    </section>
    <div class="agent-main-grid">
        <section class="agent-card agent-referral-card" data-referral-qr data-referral-url="{{ $agent->referralSource->tracking_url }}&via=qr" data-referral-copy-url="{{ $agent->referralSource->tracking_url }}" data-referral-name="{{ $agent->referralSource->code }}" data-referral-logo="{{ asset('images/favicon-512x512.png') }}">
            <div><p class="agent-kicker">YOUR PERSONAL INTRODUCTION</p><h2>One link. Every connection.</h2><p>Send customers to CuciNow for a free site assessment. Your referral code connects the enquiry back to you.</p><label class="agent-link-field"><span>Your referral link</span><input readonly value="{{ $agent->referralSource->tracking_url }}" aria-label="Your referral link"></label><div class="agent-inline"><button class="agent-button" type="button" data-copy-referral>Copy link</button><a class="agent-button agent-button-light" href="https://wa.me/?text={{ urlencode('Need professional cleaning? Book a free CuciNow site visit: '.$agent->referralSource->tracking_url.'&via=whatsapp') }}" target="_blank" rel="noopener">Share to WhatsApp ↗</a></div><p class="agent-note">Code: <strong>{{ $agent->referralSource->code }}</strong> · {{ config('agents.attribution_days') }}-day attribution</p></div>
            <div class="agent-qr-block"><canvas width="720" height="720" aria-label="Your CuciNow referral QR"></canvas><span data-qr-loading>Generating QR…</span><a class="agent-button agent-button-light" data-download-referral download="cucinow-{{ $agent->referralSource->code }}.png">Download QR</a><small>Print it. Share it. Get connected.</small></div>
        </section>
        <section class="agent-card agent-next"><p class="agent-kicker">KEEP THE MOMENTUM</p><h2>From introduction<br>to a cleaner space.</h2><ol><li><span>01</span>Share your link or branded QR.</li><li><span>02</span>Your customer requests an assessment.</li><li><span>03</span>HQ manages the visit, quote and job.</li><li><span>04</span>Follow progress in My leads.</li></ol><a href="{{ route('agents.leads') }}">See my referrals →</a></section>
    </div>
    <section class="agent-card" style="margin-top:24px"><p class="agent-kicker">GROW THE AGENT NETWORK</p><h2>Introduce a new agent</h2><p>This invitation link records you as the introducer when someone applies. It is separate from your customer referral link.</p><label class="agent-link-field"><span>Your agent invitation link</span><input readonly aria-label="Your agent invitation link" value="{{ route('agents.register', ['ref' => $agent->referralSource->code]) }}"></label></section>
    <div class="agent-section-head"><div><p class="agent-kicker">YOUR CONNECTIONS, IN MOTION</p><h2>Latest referrals</h2></div><a href="{{ route('agents.leads') }}">View all →</a></div>
    @include('agents.partials.lead-table')
    <div class="agent-section-head"><div><p class="agent-kicker">MADE BY HQ. READY FOR YOU.</p><h2>Fresh from the creative team</h2></div><a href="{{ route('agents.media') }}">Marketing tools →</a></div>
    <div class="agent-media-grid">@forelse($media as $item)<article class="agent-card agent-media-mini"><span class="agent-category">{{ ucfirst($item->category) }}</span><h3>{{ $item->title }}</h3><p>{{ Str::limit($item->description, 120) }}</p><a href="{{ route('agents.media') }}">Open library →</a></article>@empty<div class="agent-empty">HQ content will appear here when the creative team publishes it.</div>@endforelse</div>
    <div class="agent-info-strip"><strong>{{ $summary['conversion'] }}% lead-to-deal conversion</strong><span>Commission is recorded when a referred customer's invoice is fully paid, then reviewed by HQ before payout.</span></div>
</x-layouts.agent>
