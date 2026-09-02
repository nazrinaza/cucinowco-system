<div class="visit-form-card">
    @if($submitted)
        <div class="visit-success">
            <span class="success-mark">&#10003;</span>
            <p class="eyebrow">Visit request received</p>
            <h2>We’ll be in touch.</h2>
            <p>Your reference is <strong>{{ $reference }}</strong>. Our team will contact you to confirm the complimentary site visit.</p>
            <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, my free site visit reference is '.$reference) }}" target="_blank" rel="noopener" class="button">Continue on WhatsApp</a>
        </div>
    @else
        <div class="visit-form-head">
            <p><span></span> Complimentary assessment</p>
            <h2>Book your free site visit.</h2>
            <small>No obligation. Clear scope and quotation.</small>
        </div>
        <form wire:submit="submit" class="visit-form">
            @error('form')<p class="visit-form-error">{{ $message }}</p>@enderror
            <div class="visit-form-grid two">
                <label><span>Service required</span><select wire:model="serviceId">@foreach($this->services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>@error('serviceId')<small>{{ $message }}</small>@enderror</label>
                <label><span>Space type</span><select wire:model="spaceType"><option value="office">Office</option><option value="hall">Grand hall / venue</option><option value="commercial">Commercial building</option><option value="other">Other</option></select></label>
            </div>
            <div class="visit-form-grid two">
                <label><span>Your name</span><input type="text" wire:model="name" autocomplete="name" placeholder="Full name">@error('name')<small>{{ $message }}</small>@enderror</label>
                <label><span>Mobile number</span><input type="tel" wire:model="phone" autocomplete="tel" placeholder="01X-XXXXXXX">@error('phone')<small>{{ $message }}</small>@enderror</label>
            </div>
            <div class="visit-form-grid two">
                <label><span>Company <em>optional</em></span><input type="text" wire:model="companyName" autocomplete="organization" placeholder="Company name"></label>
                <label><span>Email <em>optional</em></span><input type="email" wire:model="email" autocomplete="email" placeholder="you@company.com">@error('email')<small>{{ $message }}</small>@enderror</label>
            </div>
            <div class="visit-form-grid two">
                <label><span>Preferred visit date</span><input type="date" wire:model="preferredDate" min="{{ now()->format('Y-m-d') }}">@error('preferredDate')<small>{{ $message }}</small>@enderror</label>
                <label><span>Preferred time</span><select wire:model="preferredTimeSlot"><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="flexible">Flexible</option></select></label>
            </div>
            <label><span>Site address</span><textarea wire:model="siteAddress" rows="2" placeholder="Building, unit and street"></textarea>@error('siteAddress')<small>{{ $message }}</small>@enderror</label>
            <div class="visit-form-grid compact">
                <label><span>Postcode</span><input type="text" wire:model="postcode" inputmode="numeric" placeholder="40160">@error('postcode')<small>{{ $message }}</small>@enderror</label>
                <label><span>Site notes <em>optional</em></span><input type="text" wire:model="notes" placeholder="Size, access or condition"></label>
            </div>
            <label class="form-honeypot" aria-hidden="true"><span>Website</span><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            <button type="submit" class="button visit-submit" wire:loading.attr="disabled"><span wire:loading.remove>Book my free site visit</span><span wire:loading>Sending...</span><small>No obligation</small></button>
            <p class="privacy-note">Your preferred time is subject to confirmation. No payment is required.</p>
        </form>
    @endif
</div>
