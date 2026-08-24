<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Service;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class QuoteEstimator extends Component
{
    public ?int $serviceId = null;

    public string $propertyType = 'condominium';

    public string $sizeBand = 'under_1000';

    public string $frequency = 'one_off';

    public string $preferredDate = '';

    public string $preferredTimeSlot = 'morning';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $postcode = '';

    public string $city = '';

    public string $state = 'Selangor';

    public string $notes = '';

    public bool $submitted = false;

    public string $reference = '';

    public function mount(): void
    {
        $this->serviceId = Service::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->preferredDate = now()->addDays(2)->format('Y-m-d');
    }

    #[Computed]
    public function services()
    {
        return Service::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function estimate(): float
    {
        $service = Service::find($this->serviceId);
        $base = (float) ($service?->base_price ?? 0);
        $sizeMultiplier = ['under_1000' => 1, '1000_2000' => 1.45, '2000_5000' => 2.4, 'over_5000' => 4][$this->sizeBand] ?? 1;
        $frequencyDiscount = ['one_off' => 0, 'weekly' => .15, 'fortnightly' => .10, 'monthly' => .05][$this->frequency] ?? 0;

        return round($base * $sizeMultiplier * (1 - $frequencyDiscount), 2);
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'serviceId' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'propertyType' => ['required', Rule::in(['apartment', 'condominium', 'landed', 'office', 'hall', 'other'])],
            'sizeBand' => ['required', Rule::in(['under_1000', '1000_2000', '2000_5000', 'over_5000'])],
            'frequency' => ['required', Rule::in(['one_off', 'weekly', 'fortnightly', 'monthly'])],
            'preferredDate' => ['required', 'date', 'after_or_equal:today'],
            'preferredTimeSlot' => ['required', Rule::in(['morning', 'afternoon', 'evening', 'flexible'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'postcode' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $service = Service::findOrFail($validated['serviceId']);

        DB::transaction(function () use ($validated, $service) {
            $customer = Customer::query()->firstOrNew(['phone' => $validated['phone']]);
            $customer->fill([
                'name' => $validated['name'],
                'email' => $validated['email'] ?: null,
                'type' => in_array($validated['propertyType'], ['office', 'hall']) ? 'business' : 'residential',
                'address' => $validated['address'],
                'postcode' => $validated['postcode'],
                'city' => $validated['city'],
                'state' => $validated['state'],
            ])->save();

            $this->reference = ReferenceNumber::make('Q', Quote::class, 'quote_number');
            $estimate = $this->estimate;

            $quote = Quote::create([
                'quote_number' => $this->reference,
                'customer_id' => $customer->id,
                'source' => 'website',
                'status' => 'draft',
                'property_type' => $validated['propertyType'],
                'preferred_date' => $validated['preferredDate'],
                'preferred_time_slot' => $validated['preferredTimeSlot'],
                'frequency' => $validated['frequency'],
                'service_address' => $validated['address'],
                'postcode' => $validated['postcode'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'subtotal' => $estimate,
                'total' => $estimate,
                'valid_until' => now()->addDays(config('company.quote_valid_days')),
                'customer_notes' => $validated['notes'] ?: null,
            ]);

            $quote->items()->create([
                'service_id' => $service->id,
                'description' => $service->name,
                'quantity' => 1,
                'unit' => 'job',
                'unit_price' => $estimate,
                'amount' => $estimate,
                'notes' => 'Preliminary website estimate; final scope and pricing require confirmation.',
            ]);
        });

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.quote-estimator');
    }
}
