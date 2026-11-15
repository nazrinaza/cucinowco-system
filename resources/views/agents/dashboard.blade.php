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
            <div><p class="agent-kicker">YOUR PERSONAL INTRODUCTION</p><h2>One link. Every connection.</h2><p>Send customers to CuciNow for a free site assessment. Your referral code connects the enquiry back to you.</p>@php($link = $agent->referralSource->tracking_url)
                @php($via = fn (string $channel) => $link.'&via='.$channel)
                @php($pitch = 'Need professional cleaning? Book a free CuciNow site visit: ')
                <div class="agent-link-head"><span>Your referral link</span>@if((float) $rate > 0)<span class="agent-rate-badge">{{ rtrim(rtrim(number_format((float) $rate, 2), '0'), '.') }}%</span>@endif</div>
                <label class="agent-link-field"><span class="sr-only">Your referral link</span><input readonly value="{{ $link }}" aria-label="Your referral link"></label>
                <div class="agent-share-strip" data-share-strip>
                    <button class="agent-button" type="button" data-copy-referral>Copy link</button>
                    <button type="button" class="agent-share-round" aria-expanded="false" aria-controls="referral-share-icons" title="Share" data-share-strip-toggle>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg><span class="sr-only">Share</span>
                    </button>
                    <div class="agent-share-icons" id="referral-share-icons" hidden>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($via('facebook')) }}" target="_blank" rel="noopener" title="Share on Facebook"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v6h4v-6h3l1-4h-4V8a0 0 0 0 1 0 0z"/></svg><span class="sr-only">Facebook</span></a>
                        <a href="https://x.com/intent/post?text={{ urlencode($pitch.$link) }}" target="_blank" rel="noopener" title="Share on X"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 4l16 16M20 4 4 20"/></svg><span class="sr-only">X</span></a>
                        <a href="https://www.threads.net/intent/post?text={{ urlencode($pitch.$via('threads')) }}" target="_blank" rel="noopener" title="Share on Threads"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 12v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1"/></svg><span class="sr-only">Threads</span></a>
                        <a href="https://wa.me/?text={{ urlencode($pitch.$via('whatsapp')) }}" target="_blank" rel="noopener" title="Share on WhatsApp"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.7z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.2-1.4-2-1-1 .9a4 4 0 0 1-2.2-2.2l.9-1-1-2z"/></svg><span class="sr-only">WhatsApp</span></a>
                        <a href="https://t.me/share/url?url={{ urlencode($link) }}&text={{ urlencode(rtrim($pitch, ': ')) }}" target="_blank" rel="noopener" title="Share on Telegram"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M21 4 3 11l6 2 2 6 3-4 5 4z"/><path d="m9 13 12-9"/></svg><span class="sr-only">Telegram</span></a>
                        <a href="#referral-qr" data-share-strip-qr title="Show my QR code"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/></svg><span class="sr-only">QR code</span></a>
                        <a href="mailto:?subject={{ rawurlencode('Professional cleaning by CuciNow') }}&body={{ rawurlencode($pitch.$link) }}" title="Share by email"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span class="sr-only">Email</span></a>
                    </div>
                </div><p class="agent-note">Code: <strong>{{ $agent->referralSource->code }}</strong> · {{ config('agents.attribution_days') }}-day attribution</p></div>
            <div class="agent-qr-block" id="referral-qr" tabindex="-1"><canvas width="720" height="720" aria-label="Your CuciNow referral QR"></canvas><span data-qr-loading>Generating QR…</span><a class="agent-button agent-button-light" data-download-referral download="cucinow-{{ $agent->referralSource->code }}.png">Download QR</a><small>Print it. Share it. Get connected.</small></div>
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
