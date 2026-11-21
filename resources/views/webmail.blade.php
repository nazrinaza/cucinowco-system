<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CuciNow Business Webmail</title>
    <meta name="description" content="Secure CuciNow business email sign-in through cPanel and Roundcube.">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#202328">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    @vite('resources/css/app.css')
</head>
<body class="webmail-page">
    <a href="#main" class="skip-link">Skip to sign in</a>
    <header class="webmail-site-header">
        <div class="site-shell webmail-header-inner">
            <a href="{{ route('home') }}" aria-label="CuciNow.co home">
                <img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co by Thursina" class="brand-logo">
            </a>
            <a href="{{ route('home') }}" class="webmail-header-link">Return to CuciNow.co <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <main id="main" class="webmail-hero">
        <div class="site-shell webmail-layout">
            <div class="webmail-copy">
                <p class="eyebrow"><span></span>CuciNow business mail</p>
                <h1>Your inbox.<br><em>Securely connected.</em></h1>
                <p class="webmail-lead">Sign in to your CuciNow mailbox and continue directly to Roundcube. Your credentials are submitted from your browser to the official cPanel Webmail server—not to the CuciNow application or database.</p>

                <div class="webmail-security" aria-label="Webmail security information">
                    <span aria-hidden="true">✓</span>
                    <p><strong>Direct cPanel authentication</strong><small>Encrypted connection · No credentials stored by CuciNow</small></p>
                </div>
            </div>

            <section class="webmail-card" aria-labelledby="webmail-login-title">
                <div class="webmail-card-head">
                    <div class="webmail-lock" aria-hidden="true"><span></span></div>
                    <div>
                        <p>Secure destination</p>
                        <strong>{{ $webmailHost }}</strong>
                    </div>
                </div>

                <h2 id="webmail-login-title">Sign in to Roundcube</h2>
                <form class="webmail-login-form" action="{{ $loginAction }}" method="post" autocomplete="on">
                    <input type="hidden" name="goto_uri" value="{{ $destinationPath }}">

                    <label for="webmail-user">
                        <span>Email address</span>
                        <input id="webmail-user" name="user" type="email" inputmode="email" autocomplete="username" placeholder="name@cucinow.co" required autofocus>
                    </label>

                    <label for="webmail-pass">
                        <span>Mailbox password</span>
                        <input id="webmail-pass" name="pass" type="password" autocomplete="current-password" placeholder="Enter your email password" required>
                    </label>

                    <button type="submit" name="login" class="button webmail-submit">
                        Sign in securely <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="webmail-help">This page does not send credentials through Laravel. If your account has not selected a default inbox yet, choose <strong>“Open my inbox when I log in”</strong> once in cPanel.</p>
            </section>
        </div>
    </main>

    <footer class="webmail-footer">
        <div class="site-shell">
            <span>&copy; {{ date('Y') }} CuciNow.co by Thursina.</span>
            <a href="mailto:{{ config('company.email') }}">Mailbox assistance: {{ config('company.email') }}</a>
        </div>
    </footer>
</body>
</html>
