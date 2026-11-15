@php
    $points = [
        ['Site visit first. No guesswork.', 'Every space is different, so the CuciNow team visits first, checks access, condition and measurements, then recommends the right method. There is no obligation to proceed.',
            'Sebelum bagi harga, team CuciNow datang tengok tempat dulu. Diorang check keadaan, ukuran dan akses, baru cadangkan cara yang sesuai. Tak ada obligasi pun untuk teruskan.'],
        ['Experience since 2000.', 'CuciNow.co was born from Thursina Cleaning & Services, which has served government and private-sector projects since its establishment in 2000.',
            'CuciNow ni dari Thursina Cleaning & Services. Diorang dah buat projek kerajaan dan swasta sejak tahun 2000, bukan syarikat baru.'],
        ['Fully equipped for the job.', 'Machinery, cleaning materials, chemicals and manpower are matched to each site instead of one approach for everything.',
            'Team datang dengan mesin, bahan dan tenaga kerja yang sesuai dengan tempat tu. Customer tak perlu sediakan apa-apa.'],
        ['A clear quotation.', 'The customer receives a written quotation with the scope, timing and price to approve before any work starts.',
            'Lepas site visit, customer dapat sebut harga bertulis. Skop, masa dan harga semua jelas sebelum kerja mula.'],
    ];
    $qa = [
        ['Which areas do you cover?', 'The launch focus is Klang Valley, from the Sungai Buloh operating base. Larger or outstation projects are reviewed case by case.'],
        ['How much does it cost?', 'Price depends on the space, condition and scope, so CuciNow quotes after the site visit. Please never quote a price yourself.'],
        ['Do I need to provide equipment or chemicals?', 'No. The method, equipment and materials are confirmed for each scope. Mention sensitive surfaces, pets or site restrictions early.'],
        ['Can you clean before or after an event?', 'Yes. Pre-event preparation and post-event cleaning for halls and venues, subject to access and timing.'],
        ['How early should we book?', 'Two to five days ahead is recommended. Urgent requests are reviewed based on team availability.'],
        ['Is SST included?', 'SST is shown only when legally applicable. Any tax appears clearly on the final invoice.'],
    ];
    $rules = [
        ['Introduce, don’t quote.', 'Only HQ gives prices, dates and promises about results.'],
        ['Say who you are.', 'Tell people you are a CuciNow referral partner, not staff.'],
        ['Use approved material.', 'Share HQ content from Marketing tools. Do not edit prices or claims.'],
        ['Respect privacy.', 'Share your link only where it is welcome, and never pass on customer details.'],
    ];
    $quiz = [
        ['A customer asks how much a hall deep clean costs. What do you say?', ['Give your best estimate', 'Say HQ quotes after a free site visit', 'Quote last month’s price'], 1],
        ['What happens before a quotation is sent?', ['A site visit to check the space', 'Payment of a deposit', 'Nothing, it is a fixed price list'], 0],
        ['When is your commission recorded?', ['When the customer submits the form', 'When the quotation is sent', 'When the invoice is paid in full'], 2],
        ['Who provides the machinery and chemicals?', ['The customer', 'CuciNow', 'The agent'], 1],
        ['Which area does CuciNow focus on at launch?', ['Klang Valley', 'Northern region only', 'East Malaysia only'], 0],
    ];
@endphp
<x-layouts.agent title="Sales kit">
    <div class="sk">
        <section class="sk-hero">
            <p class="agent-kicker">SALES KIT</p>
            <h1>Everything you need to<br><em>start the conversation.</em></h1>
            <p>Four reasons customers choose CuciNow, what to say in your own words, quick answers to common questions, and a short quiz to check you’re ready.</p>
            <nav class="hiw-jump" aria-label="On this page"><a href="#why">Why CuciNow</a><a href="#services">Services</a><a href="#qa">Customer Q&amp;A</a><a href="#rules">Rules</a><a href="#quiz">Quiz</a></nav>
        </section>

        <section id="why" class="sk-section" aria-labelledby="why-title">
            <h2 id="why-title" class="sk-title">Why CuciNow</h2>
            <div class="sk-points">
                @foreach($points as $i => [$title, $text, $say])
                    <article class="agent-card sk-point">
                        <p class="agent-kicker">POINT {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                        <div class="sk-say"><span>SAY THIS</span><p>“{{ $say }}”</p><button type="button" class="agent-button agent-button-light" data-agent-copy="{{ $say }}">Copy</button></div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="services" class="sk-section" aria-labelledby="services-title">
            <h2 id="services-title" class="sk-title">Services at a glance</h2>
            <p class="sk-lede">Share the matching page from Marketing tools → Page links, so the customer lands on the right service with your code.</p>
            <div class="sk-services">
                @foreach(config('cleaning-services') as $slug => $service)
                    <article class="sk-service"><h3>{{ $service['name'] }}</h3><p>{{ $service['short_description'] }}</p></article>
                @endforeach
            </div>
            <div class="sk-who"><strong>Who to approach</strong><ul>@foreach(config('site_visits.spaces') as $space)<li>{{ $space }}</li>@endforeach</ul></div>
        </section>

        <section id="qa" class="sk-section" aria-labelledby="qa-title">
            <h2 id="qa-title" class="sk-title">Customer Q&amp;A</h2>
            <div class="hiw-faq-list sk-qa">
                @foreach($qa as $i => [$q, $a])
                    <details name="sk-qa" @if($i === 0) open @endif><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
                @endforeach
            </div>
        </section>

        <section id="rules" class="sk-section" aria-labelledby="rules-title">
            <h2 id="rules-title" class="sk-title">4 rules</h2>
            <ol class="sk-rules">
                @foreach($rules as $i => [$title, $text])<li><span>{{ $i + 1 }}</span><strong>{{ $title }}</strong><p>{{ $text }}</p></li>@endforeach
            </ol>
        </section>

        <section id="quiz" class="sk-section" aria-labelledby="quiz-title">
            <h2 id="quiz-title" class="sk-title">Quick quiz</h2>
            <form class="agent-card sk-quiz" data-quiz>
                @foreach($quiz as $i => [$question, $options, $answer])
                    <fieldset data-answer="{{ $answer }}">
                        <legend><span>{{ $i + 1 }}.</span> {{ $question }}</legend>
                        @foreach($options as $j => $option)
                            <label><input type="radio" name="q{{ $i }}" value="{{ $j }}" required> {{ $option }}</label>
                        @endforeach
                    </fieldset>
                @endforeach
                <div class="mt-actions"><button type="submit" class="agent-button">Check my answers</button><button type="reset" class="agent-button agent-button-light">Try again</button></div>
                <p class="sk-quiz-result" data-quiz-result aria-live="polite"></p>
            </form>
        </section>
    </div>
</x-layouts.agent>
