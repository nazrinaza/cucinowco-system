<div class="visit-form-card">
    @if($submitted)
        <div class="visit-success">
            <span class="success-mark">&#10003;</span>
            <h2>Site visit request received.</h2>
            <p>Our team will be contacting you shortly to confirm the details, We are looking forward to serve you.</p>
            <p>Reference: <strong>{{ $reference }}</strong></p>
            <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, my free site visit reference is '.$reference) }}" target="_blank" rel="noopener" class="button">Continue on WhatsApp</a>
        </div>
    @else
        <div class="visit-form-head">
            <p><span></span> Complimentary assessment</p>
            <h2>Book your free site visit.</h2>
            <small>No obligation. Clear scope and quotation.</small>
        </div>
        <form wire:submit="submit" class="visit-form">
            @error('form')<p class="visit-form-error" role="alert">{{ $message }}</p>@enderror
            <label><span>Name</span><input type="text" wire:model="name" autocomplete="name" placeholder="Full name" required maxlength="120">@error('name')<small>{{ $message }}</small>@enderror</label>
            <div class="visit-form-grid two">
                <label><span>Email</span><input type="email" wire:model="email" autocomplete="email" placeholder="you@company.com" required maxlength="160">@error('email')<small>{{ $message }}</small>@enderror</label>
                <label><span>Phone number</span><input type="tel" wire:model="phone" autocomplete="tel" placeholder="01X-XXXXXXX" required maxlength="30">@error('phone')<small>{{ $message }}</small>@enderror</label>
            </div>
            <label><span>Space type</span><select wire:model="spaceType" required>@foreach(config('site_visits.spaces') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('spaceType')<small>{{ $message }}</small>@enderror</label>
            <fieldset class="visit-clean-types">
                <legend>Clean type <span>Select one or more</span></legend>
                <div class="visit-clean-options">
                    @foreach(config('site_visits.clean_types') as $value => $label)
                        <label wire:key="clean-{{ $value }}"><input type="checkbox" wire:model="cleanTypes" value="{{ $value }}"><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                @error('cleanTypes')<p class="visit-form-error">{{ $message }}</p>@enderror
                @error('cleanTypes.*')<p class="visit-form-error">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset class="visit-preferred">
                <legend>Preferred date and time</legend>
                <div class="visit-form-grid two">
                    <label><span>Date</span><input type="date" wire:model="preferredDate" min="{{ now()->format('Y-m-d') }}" required>@error('preferredDate')<small>{{ $message }}</small>@enderror</label>
                    <label><span>Time</span><select wire:model="preferredTimeSlot"><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="flexible">Flexible</option></select>@error('preferredTimeSlot')<small>{{ $message }}</small>@enderror</label>
                </div>
            </fieldset>
            <label class="form-honeypot" aria-hidden="true"><span>Website</span><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            <button type="submit" class="button visit-submit" wire:loading.attr="disabled"><span wire:loading.remove>Book my free site visit</span><span wire:loading>Sending...</span><small>No obligation</small></button>
            <p class="privacy-note">We’ll contact you for the location and confirm your visit. No payment required.</p>
        </form>
    @endif
</div>
