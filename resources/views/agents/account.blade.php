<x-layouts.agent title="My account">
    <div class="agent-section-head"><div><p class="agent-kicker">YOUR PLACE IN THE NETWORK</p><h1>My account</h1></div><span class="agent-status">{{ ucfirst($agent->status) }}</span></div>
    @if(!$agent->email_verified_at)<section class="agent-info-strip"><div><strong>Verify your email to continue</strong><p>We sent a verification link to {{ $agent->email }}. HQ can approve your application once your email is verified.</p></div><form method="post" action="{{ route('agents.verification.send') }}">@csrf<button class="agent-button" type="submit">Resend verification</button></form></section>@endif
    @if($agent->status !== 'approved')<section class="agent-info-strip"><div><strong>{{ $agent->status === 'pending' ? 'Your application is with HQ' : 'Your referral access is currently unavailable' }}</strong><p>{{ $agent->status === 'pending' ? 'You can update your profile while we review your application. Your dashboard and referral link will be available after approval.' : 'Contact HQ if you need help with your account status.' }}</p></div></section>@elseif(!$agent->referralSource?->is_active)<div class="agent-info-strip">Your referral link has been paused by HQ. Contact us for assistance.</div>@endif
    <div class="agent-account-grid"><section class="agent-card"><p class="agent-kicker">PARTNER DETAILS</p><h2>{{ $agent->name }}</h2><p>{{ $agent->email }}</p><p>Joined {{ $agent->created_at->format('d M Y') }}</p><p>Referral code: <strong>{{ $agent->referralSource?->code ?? $agent->requested_code }}</strong></p><p class="agent-note">Your code stays stable so printed QR codes keep working. Contact HQ to discuss account email or code changes.</p><p class="agent-note">Commission rate: <strong>{{ (float) $agent->effectiveCommissionRate() > 0 ? rtrim(rtrim($agent->effectiveCommissionRate(), '0'), '.').'%' : 'to be confirmed by HQ' }}</strong></p></section>
        <section class="agent-card"><h2>Make it yours.</h2><form class="agent-form" method="post" action="{{ route('agents.account.update') }}">@csrf @method('patch')<label>Full name<input name="name" value="{{ old('name', $agent->name) }}" required></label><div class="agent-form-row"><label>Phone<input type="tel" name="phone" value="{{ old('phone', $agent->phone) }}" required></label><label>City / state<input name="location" value="{{ old('location', $agent->location) }}" required></label></div>
            @foreach(['website', 'instagram', 'facebook', 'threads', 'tiktok'] as $social)<label>{{ ucfirst($social) }} <small>optional full URL</small><input type="url" name="social_links[{{ $social }}]" value="{{ old('social_links.'.$social, $agent->social_links[$social] ?? '') }}" placeholder="https://"></label>@endforeach
            <details class="agent-password"><summary>Change password</summary><label>Current password<input type="password" name="current_password" autocomplete="current-password"></label><label>New password<input type="password" name="password" minlength="12" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label></details><button type="submit" class="agent-button">Save changes</button>
        </form></section>
        @php($payoutErrors = $errors->getBag('payout'))
        <section class="agent-card agent-payout-card" id="payout" aria-labelledby="payout-heading">
            <div class="agent-payout-head">
                <div><p class="agent-kicker">COMMISSION PAYOUT</p><h2 id="payout-heading">Where should HQ pay you?</h2><p>Approved commission is transferred to this bank account. The name must match your bank account exactly, or the transfer may be rejected.</p></div>
                @if($agent->hasPayoutDetails())
                    <div class="agent-payout-saved"><span>Saved account</span><strong>{{ $agent->payoutBankName() }}</strong><p>{{ $agent->payout_account_name }}<br>{{ $agent->maskedPayoutAccountNumber() }}</p><small>Updated {{ $agent->payout_details_updated_at?->format('d M Y') }}</small></div>
                @else
                    <div class="agent-payout-saved is-missing"><span>No bank account yet</span><strong>Add one to receive commission</strong></div>
                @endif
            </div>
            @if($payoutErrors->any())<div class="agent-alert agent-alert-error" role="alert"><ul>@foreach($payoutErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form class="agent-form" method="post" action="{{ route('agents.account.payout') }}">@csrf @method('patch')
                <div class="agent-form-row">
                    <label>Bank<select name="payout_bank" required><option value="">Choose your bank</option>@foreach(\App\Support\MalaysianBanks::LIST as $key => $label)<option value="{{ $key }}" @selected(old('payout_bank', $agent->payout_bank) === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label>Account holder name<input name="payout_account_name" value="{{ old('payout_account_name', $agent->payout_account_name) }}" maxlength="120" autocomplete="name" placeholder="As printed on your bank account" required></label>
                </div>
                <div class="agent-form-row">
                    <label>Account number<input name="payout_account_number" value="{{ old('payout_account_number') }}" inputmode="numeric" autocomplete="off" maxlength="30" placeholder="{{ $agent->hasPayoutDetails() ? 'Enter the full number to change it' : 'e.g. 1234 5678 9012' }}" required><small>Digits only. Spaces and dashes are removed.</small></label>
                    <label>Current password<input type="password" name="payout_password" autocomplete="current-password" required><small>Needed to protect your payouts.</small></label>
                </div>
                <button type="submit" class="agent-button">{{ $agent->hasPayoutDetails() ? 'Update bank details' : 'Save bank details' }}</button>
            </form>
        </section>
    </div>
</x-layouts.agent>
