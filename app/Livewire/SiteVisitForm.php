<?php

namespace App\Livewire;

use App\Mail\NewSiteVisitNotificationMail;
use App\Mail\SiteVisitConfirmationMail;
use App\Models\Customer;
use App\Models\SiteVisitRequest;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SiteVisitForm extends Component
{
    public array $cleanTypes = [];

    public string $spaceType = 'office';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $preferredDate = '';

    public string $preferredTimeSlot = 'morning';

    public string $website = '';

    public bool $submitted = false;

    public string $reference = '';

    public function mount(): void
    {
        $this->preferredDate = now()->addDays(2)->format('Y-m-d');
    }

    public function submit(): void
    {
        if (! RateLimiter::attempt('site-visit:'.request()->ip(), 5, fn () => true, 60)) {
            $this->addError('form', 'Too many requests. Please wait a minute or contact us on WhatsApp.');

            return;
        }

        $validated = $this->validate([
            'cleanTypes' => ['required', 'array', 'min:1', 'max:6'],
            'cleanTypes.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('site_visits.clean_types')))],
            'spaceType' => ['required', Rule::in(array_keys(config('site_visits.spaces')))],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'preferredDate' => ['required', 'date', 'after_or_equal:today'],
            'preferredTimeSlot' => ['required', Rule::in(['morning', 'afternoon', 'flexible'])],
            'website' => ['prohibited'],
        ]);

        $siteVisit = DB::transaction(function () use ($validated) {
            $customer = Customer::query()->firstOrNew(['phone' => $validated['phone']]);
            $customer->fill([
                'name' => $validated['name'],
                'email' => $validated['email'] ?: null,
                'type' => 'business',
            ])->save();

            $this->reference = ReferenceNumber::make('SV', SiteVisitRequest::class, 'reference_number');

            return SiteVisitRequest::create([
                'reference_number' => $this->reference,
                'customer_id' => $customer->id,
                'clean_types' => array_values($validated['cleanTypes']),
                'source' => 'website',
                'status' => 'new',
                'space_type' => $validated['spaceType'],
                'preferred_date' => $validated['preferredDate'],
                'preferred_time_slot' => $validated['preferredTimeSlot'],
                'site_address' => '',
            ]);
        });

        $siteVisit->load(['customer', 'service']);

        if ($siteVisit->customer->email) {
            Mail::to($siteVisit->customer->email, $siteVisit->customer->name)
                ->queue(new SiteVisitConfirmationMail($siteVisit));
        }

        if (config('company.notifications_email')) {
            Mail::to(config('company.notifications_email'), config('company.name'))
                ->queue(new NewSiteVisitNotificationMail($siteVisit));
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.site-visit-form');
    }
}
