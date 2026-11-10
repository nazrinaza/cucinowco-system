<?php

namespace App\Livewire;

use App\Jobs\SendWhatsAppEnquiryAcknowledgement;
use App\Models\WhatsAppEnquiry;
use App\Support\ClientIpResolver;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class WhatsAppEnquiryForm extends Component
{
    public bool $open = false;

    public bool $submitted = false;

    public bool $consent = false;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $message = '';

    public string $website = '';

    public string $reference = '';

    public function openForm(): void
    {
        $this->open = true;
    }

    public function closeForm(): void
    {
        $this->open = false;
    }

    public function submit(): void
    {
        if (! RateLimiter::attempt('whatsapp-enquiry:'.request()->ip(), 5, fn () => true, 60)) {
            $this->addError('form', 'Too many requests. Please wait a minute and try again.');

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'message' => ['required', 'string', 'max:1000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ]);

        $this->reference = ReferenceNumber::make('WA', WhatsAppEnquiry::class, 'reference_number');
        $enquiry = WhatsAppEnquiry::create([
            'reference_number' => $this->reference,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?: null,
            'message' => $validated['message'],
            'whatsapp_consent' => true,
            'client_ip' => app(ClientIpResolver::class)->resolve(request()),
            'referral_code' => session('cucinow.referral_code', 'direct'),
            'landing_url' => session('cucinow.referral_landing_url'),
        ]);

        SendWhatsAppEnquiryAcknowledgement::dispatch($enquiry)->afterCommit();
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.whats-app-enquiry-form');
    }
}
