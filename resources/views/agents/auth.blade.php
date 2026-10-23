@php($headings = ['register' => 'Good connections. Cleaner spaces.', 'login' => 'Welcome back, partner.', 'forgot' => 'Let’s get you back in.', 'reset' => 'A fresh start.'])
<x-layouts.agent :title="$headings[$mode]">
    <div class="agent-auth-grid">
        <section class="agent-auth-story">
            <p class="agent-kicker">THE CUCINOW AGENT NETWORK</p>
            <h1>Your network.<br>Our expertise.<br><em>New possibilities.</em></h1>
            <p>Connect offices, halls and businesses with professional cleaning. Share your link, follow every referral and get the right tools from HQ.</p>
            <div class="agent-auth-features"><span>01 <strong>A link that’s yours</strong></span><span>02 <strong>Ready-to-share content</strong></span><span>03 <strong>Clear lead progress</strong></span></div>
            <a href="{{ route('agents.guide') }}">Explore the programme →</a>
        </section>
        <section class="agent-card agent-auth-form">
            <p class="agent-kicker">{{ $mode === 'register' ? 'BECOME A PARTNER' : 'AGENT ACCESS' }}</p>
            <h2>{{ $headings[$mode] }}</h2>
            @if($mode === 'register')
                <p>Tell us a little about yourself. Verify your email and HQ will review your application.</p>
                <form method="post" action="{{ route('agents.register.store') }}" class="agent-form">@csrf
                    <label>Full name<input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required></label>
                    <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
                    <div class="agent-form-row"><label>Phone number<input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required></label><label>City / state<input name="location" value="{{ old('location') }}" placeholder="e.g. Shah Alam, Selangor" required></label></div>
                    <label>Choose your referral code<input name="requested_code" value="{{ old('requested_code') }}" pattern="[a-z0-9][a-z0-9_-]{2,39}" placeholder="e.g. ahmad" minlength="3" maxlength="40" required><small>3–40 lowercase letters, numbers, hyphens or underscores. Activated after HQ approval.</small></label>
                    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="12" required><small>At least 12 characters.</small></label>
                    <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
                    <label class="agent-check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>I have read the <a href="{{ route('agents.guide') }}" target="_blank">programme guide and sharing rules</a>. I understand that commissions and payouts have not yet been configured.</span></label>
                    <label class="form-honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
                    <button class="agent-button" type="submit">Apply to join →</button>
                    <p>Already a member? <a href="{{ route('agents.login') }}">Sign in</a></p>
                </form>
            @elseif($mode === 'login')
                <p>Your referrals, marketing tools and progress, all in one place.</p>
                <form method="post" action="{{ route('agents.login.store') }}" class="agent-form">@csrf
                    <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
                    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
                    <div class="agent-inline"><label class="agent-check"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label><a href="{{ route('agents.password.request') }}">Forgot password?</a></div>
                    <button class="agent-button" type="submit">Sign in →</button>
                    <p>New to the network? <a href="{{ route('agents.register') }}">Become an agent</a></p>
                </form>
            @elseif($mode === 'forgot')
                <p>Enter your agent email and we’ll send a password reset link.</p>
                <form method="post" action="{{ route('agents.password.email') }}" class="agent-form">@csrf<label>Email address<input type="email" name="email" value="{{ old('email') }}" required></label><button type="submit" class="agent-button">Send reset link</button></form>
            @else
                <form method="post" action="{{ route('agents.password.update') }}" class="agent-form">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email address<input type="email" name="email" value="{{ old('email', $email) }}" required></label><label>New password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit" class="agent-button">Reset password</button></form>
            @endif
        </section>
    </div>
</x-layouts.agent>
