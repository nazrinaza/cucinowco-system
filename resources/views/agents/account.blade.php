<x-layouts.agent title="My account">
    <div class="agent-section-head"><div><p class="agent-kicker">YOUR PLACE IN THE NETWORK</p><h1>My account</h1></div><span class="agent-status">{{ ucfirst($agent->status) }}</span></div>
    @if(!$agent->email_verified_at)<section class="agent-info-strip"><div><strong>Verify your email to continue</strong><p>We sent a verification link to {{ $agent->email }}. HQ can approve your application once your email is verified.</p></div><form method="post" action="{{ route('agents.verification.send') }}">@csrf<button class="agent-button" type="submit">Resend verification</button></form></section>@endif
    @if($agent->status !== 'approved')<section class="agent-info-strip"><div><strong>{{ $agent->status === 'pending' ? 'Your application is with HQ' : 'Your referral access is currently unavailable' }}</strong><p>{{ $agent->status === 'pending' ? 'You can update your profile while we review your application. Your dashboard and referral link will be available after approval.' : 'Contact HQ if you need help with your account status.' }}</p></div></section>@elseif(!$agent->referralSource?->is_active)<div class="agent-info-strip">Your referral link has been paused by HQ. Contact us for assistance.</div>@endif

    {{-- Profile --}}
    <section class="agent-card acct-section" aria-labelledby="profile-heading">
        <div class="acct-meta">
            <h2 id="profile-heading">Profile</h2>
            <p>Update your profile</p>
            <dl>
                <div><dt>Code</dt><dd><strong>{{ $agent->referralSource?->code ?? $agent->requested_code }}</strong></dd></div>
                <div><dt>Joined</dt><dd>{{ $agent->created_at->format('d M Y') }}</dd></div>
                <div><dt>Email</dt><dd>{{ $agent->email }}</dd></div>
                <div><dt>Commission</dt><dd>{{ (float) $agent->effectiveCommissionRate() > 0 ? rtrim(rtrim($agent->effectiveCommissionRate(), '0'), '.').'%' : 'Set by HQ' }}</dd></div>
            </dl>
            <p class="agent-note">Your code stays the same so printed QR codes keep working. Contact HQ to change your email or code.</p>
        </div>
        <div class="acct-body">
            @php($photoErrors = $errors->getBag('photo'))
            <div class="acct-photo">
                <span class="acct-label">PROFILE PHOTO</span>
                <div class="acct-photo-row">
                    @if($agent->profile_photo_path)
                        <img src="{{ route('agents.account.photo') }}?v={{ $agent->updated_at?->timestamp }}" alt="Your profile photo" class="acct-photo-img" width="160" height="160">
                    @else
                        <span class="acct-photo-img acct-photo-empty" aria-hidden="true">{{ mb_substr($agent->name, 0, 1) }}</span>
                    @endif
                    <div class="acct-photo-actions">
                        <form method="post" action="{{ route('agents.account.photo.update') }}" enctype="multipart/form-data" data-auto-submit>@csrf
                            <label class="agent-button agent-button-light acct-upload">{{ $agent->profile_photo_path ? 'Change photo' : 'Upload photo' }}<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" required></label>
                            <noscript><button class="agent-button">Upload</button></noscript>
                        </form>
                        @if($agent->profile_photo_path)
                            <form method="post" action="{{ route('agents.account.photo.delete') }}">@csrf @method('delete')<button type="submit" class="acct-link">Delete</button></form>
                        @endif
                        <small>JPG, PNG or WebP, up to 2 MB.</small>
                        @if($photoErrors->any())<small class="acct-error">{{ $photoErrors->first() }}</small>@endif
                    </div>
                </div>
            </div>

            <form class="agent-form acct-form" method="post" action="{{ route('agents.account.update') }}">@csrf @method('patch')
                <div class="agent-form-row">
                    <label><span class="acct-label">YOUR NAME <em>*</em></span><input name="name" value="{{ old('name', $agent->name) }}" maxlength="120" autocomplete="name" required></label>
                    <label><span class="acct-label">PHONE <em>*</em></span>
                        <span class="acct-prefixed"><span class="acct-prefix acct-flag" aria-hidden="true">🇲🇾 +60</span><input type="tel" name="phone_local" value="{{ old('phone_local', $agent->phoneLocal()) }}" inputmode="tel" autocomplete="tel-national" placeholder="12-345 6789" maxlength="15" required aria-label="Phone number after +60"></span>
                        @error('phone_local')<small class="acct-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <label><span class="acct-label">CITY / STATE <em>*</em></span><input name="location" value="{{ old('location', $agent->location) }}" maxlength="120" placeholder="e.g. Shah Alam, Selangor" required></label>

                <label><span class="acct-label">WEBSITE</span>
                    <span class="acct-prefixed"><span class="acct-prefix">https://</span><input name="social_links[website]" value="{{ old('social_links.website', $agent->socialHandle('website')) }}" inputmode="url" autocapitalize="none" spellcheck="false" placeholder="yourwebsite.com" aria-label="Website address after https://"></span>
                    @error('social_links.website')<small class="acct-error">{{ $message }}</small>@enderror
                </label>
                <div class="agent-form-row">
                    @foreach(['instagram', 'tiktok', 'threads', 'x'] as $key)
                        @php([$label, $prefix] = \App\Models\Agent::SOCIALS[$key])
                        <label><span class="acct-label">{{ strtoupper($label) }}</span>
                            <span class="acct-prefixed"><span class="acct-prefix">{{ $prefix }}</span><input name="social_links[{{ $key }}]" value="{{ old('social_links.'.$key, $agent->socialHandle($key)) }}" autocapitalize="none" spellcheck="false" placeholder="username" aria-label="{{ $label }} username"></span>
                            @error('social_links.'.$key)<small class="acct-error">{{ $message }}</small>@enderror
                        </label>
                    @endforeach
                </div>
                @foreach(['youtube', 'facebook'] as $key)
                    @php([$label, $prefix] = \App\Models\Agent::SOCIALS[$key])
                    <label><span class="acct-label">{{ strtoupper($label) }}</span>
                        <span class="acct-prefixed"><span class="acct-prefix">{{ $prefix }}</span><input name="social_links[{{ $key }}]" value="{{ old('social_links.'.$key, $agent->socialHandle($key)) }}" autocapitalize="none" spellcheck="false" placeholder="{{ $key === 'facebook' ? 'yourpage' : 'channel' }}" aria-label="{{ $label }} {{ $key === 'facebook' ? 'page' : 'channel' }}"></span>
                        @error('social_links.'.$key)<small class="acct-error">{{ $message }}</small>@enderror
                    </label>
                @endforeach
                <small class="agent-note">Paste a full profile link or just your username. We tidy it up for you.</small>

                <details class="agent-password" @if($errors->has('current_password') || $errors->has('password')) open @endif><summary>Change password</summary><label>Current password<input type="password" name="current_password" autocomplete="current-password"></label><label>New password<input type="password" name="password" minlength="12" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label></details>
                <button type="submit" class="agent-button">Save changes</button>
            </form>
        </div>
    </section>

    {{-- Payout --}}
    @php($payoutErrors = $errors->getBag('payout'))
    <section class="agent-card acct-section" id="payout" aria-labelledby="payout-heading">
        <div class="acct-meta">
            <h2 id="payout-heading">Payment settings</h2>
            <p>Where HQ pays your commission</p>
            @if($agent->hasPayoutDetails())
                <div class="agent-payout-saved"><span>Saved account</span><strong>{{ $agent->payoutBankName() }}</strong><p>{{ $agent->payout_account_name }}<br>{{ $agent->maskedPayoutAccountNumber() }}</p><small>Updated {{ $agent->payout_details_updated_at?->format('d M Y') }}</small></div>
            @else
                <div class="agent-payout-saved is-missing"><span>No bank account yet</span><strong>Add one to receive commission</strong></div>
            @endif
        </div>
        <div class="acct-body">
            <p class="acct-intro">Approved commission is transferred to this bank account. The name must match your bank account exactly, or the transfer may be rejected.</p>
            @if($payoutErrors->any())<div class="agent-alert agent-alert-error" role="alert"><ul>@foreach($payoutErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form class="agent-form acct-form" method="post" action="{{ route('agents.account.payout') }}">@csrf @method('patch')
                <div class="agent-form-row">
                    <label><span class="acct-label">BANK <em>*</em></span><select name="payout_bank" required><option value="">Choose your bank</option>@foreach(\App\Support\MalaysianBanks::LIST as $key => $label)<option value="{{ $key }}" @selected(old('payout_bank', $agent->payout_bank) === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label><span class="acct-label">ACCOUNT HOLDER NAME <em>*</em></span><input name="payout_account_name" value="{{ old('payout_account_name', $agent->payout_account_name) }}" maxlength="120" autocomplete="name" placeholder="As printed on your bank account" required></label>
                </div>
                <div class="agent-form-row">
                    <label><span class="acct-label">ACCOUNT NUMBER <em>*</em></span><input name="payout_account_number" value="{{ old('payout_account_number') }}" inputmode="numeric" autocomplete="off" maxlength="30" placeholder="{{ $agent->hasPayoutDetails() ? 'Enter the full number to change it' : 'e.g. 1234 5678 9012' }}" required><small>Digits only. Spaces and dashes are removed.</small></label>
                    <label><span class="acct-label">CURRENT PASSWORD <em>*</em></span><input type="password" name="payout_password" autocomplete="current-password" required><small>Needed to protect your payouts.</small></label>
                </div>
                <button type="submit" class="agent-button">{{ $agent->hasPayoutDetails() ? 'Update bank details' : 'Save bank details' }}</button>
            </form>
        </div>
    </section>
</x-layouts.agent>
