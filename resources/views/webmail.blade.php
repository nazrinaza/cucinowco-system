<x-layouts.public
    title="CuciNow Business Webmail"
    description="Secure access to CuciNow business email through the official cPanel Webmail service."
    robots="noindex,nofollow"
>
    <section class="webmail-hero">
        <div class="site-shell webmail-layout">
            <div class="webmail-copy">
                <p class="eyebrow"><span></span>CuciNow business mail</p>
                <h1>Your inbox.<br><em>Securely connected.</em></h1>
                <p class="webmail-lead">Continue to the official cPanel Webmail sign-in to open Roundcube. Your mailbox password is entered only on the secure Webmail server—never on this page.</p>

                <div class="webmail-actions">
                    <a class="button webmail-button" href="{{ $webmailUrl }}" target="_blank" rel="noopener noreferrer">
                        Open secure Webmail <span aria-hidden="true">↗</span>
                    </a>
                    <a class="webmail-home-link" href="{{ route('home') }}">Return to CuciNow.co</a>
                </div>

                <div class="webmail-security" aria-label="Webmail security information">
                    <span aria-hidden="true">✓</span>
                    <p><strong>Official cPanel sign-in</strong><small>Encrypted connection · No password collected here</small></p>
                </div>
            </div>

            <aside class="webmail-card" aria-labelledby="webmail-guide-title">
                <div class="webmail-card-head">
                    <div class="webmail-lock" aria-hidden="true"><span></span></div>
                    <div>
                        <p>Secure destination</p>
                        <strong>{{ $webmailHost }}</strong>
                    </div>
                </div>

                <h2 id="webmail-guide-title">Sign in to Roundcube</h2>
                <ol class="webmail-steps">
                    <li><span>01</span><p><strong>Enter your full email</strong><small>For example, name@cucinow.co</small></p></li>
                    <li><span>02</span><p><strong>Enter your mailbox password</strong><small>Only on the official cPanel page</small></p></li>
                    <li><span>03</span><p><strong>Open your inbox</strong><small>Select “Open my inbox when I log in” for direct Roundcube access next time</small></p></li>
                </ol>

                <p class="webmail-help">Need mailbox assistance? Email <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</p>
            </aside>
        </div>
    </section>
</x-layouts.public>
