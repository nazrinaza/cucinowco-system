<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SiteVisitForm extends Component
{
    private const UNAVAILABLE_SERVICE_CODES = ['home-cleaning', 'kitchen-hood'];

    public ?int $serviceId = null;

    public string $spaceType = 'office';

    public string $companyName = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $preferredDate = '';

    public string $preferredTimeSlot = 'morning';

    public string $siteAddress = '';

    public string $postcode = '';

    public string $notes = '';

    public string $website = '';

    public bool $submitted = false;

    public string $reference = '';

    public function mount(): void
    {
        $this->serviceId = $this->services->first()?->id;
        $this->preferredDate = now()->addDays(2)->format('Y-m-d');
    }

    #[Computed]
    public function services()
    {
        return Service::query()
            ->where('is_active', true)
            ->whereNotIn('code', self::UNAVAILABLE_SERVICE_CODES)
            ->orderBy('sort_order')
            ->get();
    }

    public function submit(): void
    {
        if (! RateLimiter::attempt('site-visit:'.request()->ip(), 5, fn () => true, 60)) {
            $this->addError('form', 'Too many requests. Please wait a minute or contact us on WhatsApp.');

            return;
        }

        $validated = $this->validate([
            'serviceId' => ['required', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNotIn('code', self::UNAVAILABLE_SERVICE_CODES))],
            'spaceType' => ['required', Rule::in(['office', 'hall', 'commercial', 'other'])],
            'companyName' => ['nullable', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'preferredDate' => ['required', 'date', 'after_or_equal:today'],
            'preferredTimeSlot' => ['required', Rule::in(['morning', 'afternoon', 'flexible'])],
            'siteAddress' => ['required', 'string', 'max:1000'],
            'postcode' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'website' => ['prohibited'],
        ]);

        DB::transaction(function () use ($validated) {
            $customer = Customer::query()->firstOrNew(['phone' => $validated['phone']]);
            $customer->fill([
                'name' => $validated['name'],
                'email' => $validated['email'] ?: null,
                'type' => 'business',
                'company_name' => $validated['companyName'] ?: null,
                'address' => $validated['siteAddress'],
                'postcode' => $validated['postcode'],
            ])->save();

            $this->reference = ReferenceNumber::make('SV', SiteVisitRequest::class, 'reference_number');

            SiteVisitRequest::create([
                'reference_number' => $this->reference,
                'customer_id' => $customer->id,
                'service_id' => $validated['serviceId'],
                'source' => 'website',
                'status' => 'new',
                'space_type' => $validated['spaceType'],
                'preferred_date' => $validated['preferredDate'],
                'preferred_time_slot' => $validated['preferredTimeSlot'],
                'site_address' => $validated['siteAddress'],
                'postcode' => $validated['postcode'],
                'customer_notes' => $validated['notes'] ?: null,
            ]);
        });

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.site-visit-form');
    }
}
