<div class="whatsapp-enquiry" wire:keydown.escape.window="closeForm">
    <button class="whatsapp-float" type="button" wire:click="openForm" aria-haspopup="dialog">WhatsApp <span>Ask our team</span></button>
    @if($open)
        <div class="whatsapp-modal-backdrop" wire:click.self="closeForm">
            <section class="whatsapp-modal" role="dialog" aria-modal="true" aria-labelledby="whatsapp-enquiry-title">
                <button class="whatsapp-modal-close" type="button" wire:click="closeForm" aria-label="Close">&times;</button>
                @if($submitted)
                    <p class="eyebrow"><span></span> Enquiry received</p><h2 id="whatsapp-enquiry-title">Thank you, {{ $name }}.</h2>
                    <p>We saved your request and will contact you shortly on WhatsApp.</p><p class="whatsapp-reference">Reference: <strong>{{ $reference }}</strong></p>
                    <button class="button" type="button" wire:click="closeForm">Close</button>
                @else
                    <p class="eyebrow"><span></span> WhatsApp enquiry</p><h2 id="whatsapp-enquiry-title">Tell us how we can help.</h2>
                    <p>Submit your details first so our team can respond with the right context.</p>
                    <form wire:submit="submit" class="whatsapp-form">
                        <label><span>Name</span><input type="text" wire:model="name" autocomplete="name" required>@error('name')<small>{{ $message }}</small>@enderror</label>
                        <label><span>Phone number</span><input type="tel" wire:model="phone" autocomplete="tel" placeholder="+60 11-xxxx xxxx" required>@error('phone')<small>{{ $message }}</small>@enderror</label>
                        <label><span>Email <em>Optional</em></span><input type="email" wire:model="email" autocomplete="email">@error('email')<small>{{ $message }}</small>@enderror</label>
                        <label><span>How can we help?</span><textarea wire:model="message" rows="4" required></textarea>@error('message')<small>{{ $message }}</small>@enderror</label>
                        <label class="whatsapp-consent"><input type="checkbox" wire:model="consent"><span>I agree to receive a WhatsApp response from CuciNow.co about this enquiry.</span></label>
                        @error('consent')<small class="field-error">{{ $message }}</small>@enderror
                        <input class="form-honeypot" type="text" wire:model="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                        @error('form')<div class="admin-alert error">{{ $message }}</div>@enderror
                        <button class="button" type="submit" wire:loading.attr="disabled">Submit enquiry <span wire:loading>…</span></button>
                    </form>
                @endif
            </section>
        </div>
    @endif
</div>
