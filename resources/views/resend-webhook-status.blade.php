<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Email Webhook Ready | CuciNow.co</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
</head>
<body>
<main style="min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f4ec">
    <section class="admin-card" style="width:min(560px,100%);padding:42px;text-align:center">
        <img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co" style="width:210px;margin:0 auto 28px">
        <p class="admin-kicker">Email integration</p>
        <h1 style="margin-bottom:14px">Webhook endpoint is ready.</h1>
        <p style="color:var(--muted);line-height:1.7">This address securely receives signed email delivery events from Resend. Browser visits use GET; Resend delivers events through an authenticated POST request.</p>
        <div style="margin:24px 0;padding:14px;color:#15563f;background:#dff4ea;border-radius:10px;font-weight:800">Status: Ready to receive events</div>
        <a href="{{ route('home') }}" class="admin-button">Return to CuciNow.co</a>
    </section>
</main>
</body>
</html>
