<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SiteVisitRequest;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteController extends Controller
{
    private const STATUSES = ['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired'];

    public function create(Request $request): View
    {
        $siteVisit = $request->integer('site_visit')
            ? SiteVisitRequest::with(['customer', 'service'])->findOrFail($request->integer('site_visit'))
            : null;

        return view('admin.quotes.create', compact('siteVisit'));
    }

    public function index(Request $request): View
    {
        $quotes = Quote::with('customer')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($nested) => $nested->where('quote_number', 'like', "%{$term}%")->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.quotes.index', ['quotes' => $quotes, 'statuses' => self::STATUSES]);
    }

    public function show(Quote $quote): View
    {
        $quote->load(['customer', 'items.service', 'invoice', 'booking']);

        return view('admin.quotes.show', ['quote' => $quote, 'statuses' => self::STATUSES]);
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'internal_notes' => ['nullable', 'string', 'max:3000']]);
        $updates = ['status' => $data['status'], 'internal_notes' => $data['internal_notes'] ?? null];
        $timestamp = ['sent' => 'sent_at', 'viewed' => 'viewed_at', 'accepted' => 'accepted_at', 'rejected' => 'rejected_at'][$data['status']] ?? null;
        if ($timestamp) {
            $updates[$timestamp] = now();
        }
        $quote->update($updates);

        return back()->with('success', 'Quote updated.');
    }

    public function convert(Quote $quote): RedirectResponse
    {
        if ($quote->invoice) {
            return redirect()->route('admin.invoices.show', $quote->invoice);
        }

        $invoice = DB::transaction(function () use ($quote) {
            $taxRate = config('company.sst_enabled') ? config('company.sst_rate') : 0;
            $taxAmount = round(((float) $quote->subtotal - (float) $quote->discount) * $taxRate / 100, 2);
            $total = (float) $quote->subtotal - (float) $quote->discount + $taxAmount;
            $invoice = Invoice::create([
                'invoice_number' => ReferenceNumber::make('INV', Invoice::class, 'invoice_number'),
                'customer_id' => $quote->customer_id, 'quote_id' => $quote->id, 'status' => 'draft',
                'issued_at' => today(), 'due_at' => today()->addDays(14), 'subtotal' => $quote->subtotal,
                'discount' => $quote->discount, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount,
                'total' => $total, 'balance' => $total, 'payment_terms' => 'Due within 14 days',
            ]);
            foreach ($quote->items as $item) {
                $invoice->items()->create($item->only(['description', 'quantity', 'unit', 'unit_price', 'amount']));
            }
            $quote->update(['status' => 'accepted', 'accepted_at' => $quote->accepted_at ?? now()]);

            return $invoice;
        });

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice created from quote.');
    }

    public function book(Request $request, Quote $quote): RedirectResponse
    {
        if ($quote->booking) {
            return redirect()->route('admin.bookings.index')->with('success', 'This quote already has a booking.');
        }

        $data = $request->validate([
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Booking::create([
            ...$data,
            'booking_number' => ReferenceNumber::make('BK', Booking::class, 'booking_number'),
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'service_id' => $quote->items()->value('service_id'),
            'status' => 'confirmed',
            'service_address' => $quote->service_address,
            'total' => $quote->total,
        ]);

        $quote->update(['status' => 'accepted', 'accepted_at' => $quote->accepted_at ?? now()]);

        return redirect()->route('admin.bookings.index')->with('success', 'Booking created and added to the schedule.');
    }
}
