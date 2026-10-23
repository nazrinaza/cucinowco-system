@props(['title' => 'Agent network'])
@php($portalAgent = Auth::guard('agent')->user())
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} | CuciNow Agents</title>
    <meta name="description" content="Join the CuciNow agent network. Share your personal referral link, follow referred enquiries and access ready-to-share content from HQ.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="CuciNow Agent Network">
    <meta property="og:title" content="{{ $title }} | CuciNow Agent Network">
    <meta property="og:description" content="Your network. Our expertise. New possibilities. Join the CuciNow agent network.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/agents-ogimage.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('images/agents-ogimage.png') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="The CuciNow Agent Network — Your network. Our expertise. New possibilities.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} | CuciNow Agent Network">
    <meta name="twitter:description" content="Your network. Our expertise. New possibilities. Join the CuciNow agent network.">
    <meta name="twitter:image" content="{{ asset('images/agents-ogimage.png') }}">
    <meta name="twitter:image:alt" content="The CuciNow Agent Network — Your network. Our expertise. New possibilities.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="agent-body">
    <a href="#agent-main" class="skip-link">Skip to content</a>
    <header class="agent-header">
        <a href="{{ route('agents.dashboard') }}" class="agent-brand"><img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow by Thursina"><span>AGENT NETWORK</span></a>
        <div class="agent-header-right"><a href="{{ config('agents.public_url') }}" target="_blank" rel="noopener">Visit CuciNow ↗</a>@if($portalAgent)<span class="agent-avatar">{{ mb_substr($portalAgent->name, 0, 1) }}</span>@endif</div>
    </header>
    <div class="agent-shell">
        <nav class="agent-nav" aria-label="Agent navigation">
            @if($portalAgent)
                @foreach(['dashboard' => 'Overview', 'media' => 'Marketing tools', 'leads' => 'My leads', 'analytics' => 'Analytics', 'guide' => 'Platform guide', 'account' => 'My account'] as $key => $label)
                    <a href="{{ route('agents.'.$key) }}" @class(['active' => request()->routeIs('agents.'.$key)])>{{ $label }}</a>
                @endforeach
                <form action="{{ route('agents.logout') }}" method="post">@csrf<button type="submit">Sign out</button></form>
            @else
                <a href="{{ route('agents.register') }}" @class(['active' => request()->routeIs('agents.register')])>Become an agent</a>
                <a href="{{ route('agents.guide') }}" @class(['active' => request()->routeIs('agents.guide')])>How it works</a>
                <a href="{{ route('agents.login') }}" @class(['active' => request()->routeIs('agents.login')])>Agent sign in</a>
            @endif
        </nav>
        <main id="agent-main">
            @if(session('success'))<div class="agent-alert" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="agent-alert agent-alert-error" role="alert"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </main>
        <footer class="agent-footer"><span>© {{ now()->year }} CuciNow.co by Thursina</span><span>Better spaces. Stronger connections.</span><a href="mailto:agents@cucinow.co">Contact HQ</a></footer>
    </div>
</body>
</html>
