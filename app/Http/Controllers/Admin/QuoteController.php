<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmationMail;
use App\Mail\QuoteMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SiteVisitRequest;
use App\Support\DocumentEmailHistory;
use App\Support\DocumentEmailRecipients;
use App\Support\ReferenceNumber;
use App\Support\QuotationHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuoteController extends Controller
{
    private const STATUSES = ['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired'];

    public function create(Request $request): View
    {
        $siteVisit = $request->integer('site_visit')
            ? SiteVisitRequest::with(['customer', 'service', 'referralSource'])->findOrFail($request->integer('site_visit'))
            : null;

        return view('admin.quotes.create', compact('siteVisit'));
    }

    public function index(Request $request): View
    {
        $quotes = Quote::with(['customer', 'siteVisit.referralSource'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($nested) => $nested->where('quote_number', 'like', "%{$term}%")->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.quotes.index', ['quotes' => $quotes, 'statuses' => self::STATUSES]);
    }

    public function show(Quote $quote): View
    {
        $quote->load(['customer', 'items.service', 'invoice', 'booking', 'siteVisit.referralSource', 'createdBy', 'lastEditedBy', 'sentBy']);
        $availableSiteVisits = SiteVisitRequest::query()
            ->where('customer_id', $quote->customer_id)
            ->latest()
            ->limit(100)
            ->get(['id', 'reference_number', 'preferred_date', 'status']);

        return view('admin.quotes.show', [
            'quote' => $quote, 'statuses' => self::STATUSES,
            'availableSiteVisits' => $availableSiteVisits,
            'emailHistory' => app(DocumentEmailHistory::class)->forDocument($quote),
        ]);
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'internal_notes' => ['nullable', 'string', 'max:3000']]);
        $updates = ['status' => $data['status'], 'internal_notes' => $data['internal_notes'] ?? null, 'last_edited_by_user_id' => $request->user()->id];
        $timestamp = ['sent' => 'sent_at', 'viewed' => 'viewed_at', 'accepted' => 'accepted_at', 'rejected' => 'rejected_at'][$data['status']] ?? null;
        if ($timestamp) {
            $updates[$timestamp] = now();
        }
        $quote->update($updates);

        return back()->with('success', 'Quote updated.');
    }

    public function updateItems(Request $request, Quote $quote, QuotationHtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:id,description,notes,quantity,unit,unit_price'],
            'items.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('quote_items', 'id')->where('quote_id', $quote->id)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:3000'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'discount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($quote, $data, $request, $sanitizer) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($quote->invoice()->exists() || $quote->booking()->exists()) {
                throw ValidationException::withMessages(['items' => 'This quotation already has an invoice or booking. Create a separate quotation for additional work.']);
            }

            $subtotalCents = 0;
            $lines = [];
            foreach ($data['items'] as $line) {
                $line['notes'] = $sanitizer->sanitize($line['notes'] ?? null) ?: null;
                $quantityHundredths = (int) round((float) $line['quantity'] * 100);
                $rateCents = (int) round((float) $line['unit_price'] * 100);
                // Round each line to the nearest sen before summing the quotation.
                $amountCents = intdiv($quantityHundredths * $rateCents + 50, 100);
                $subtotalCents += $amountCents;
                $lines[] = [...$line, 'amount' => $amountCents / 100];
            }

            if ($subtotalCents > 999999999999) {
                throw ValidationException::withMessages(['items' => 'The quotation total is too large. Reduce the quantities or rates.']);
            }

            $discountCents = (int) round((float) $data['discount'] * 100);
            if ($discountCents > $subtotalCents) {
                throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the services subtotal.']);
            }

            $taxBasisPoints = (int) round((float) $quote->tax_rate * 100);
            $taxCents = intdiv(($subtotalCents - $discountCents) * $taxBasisPoints + 5000, 10000);
            $totalCents = $subtotalCents - $discountCents + $taxCents;
            if (max($subtotalCents, $totalCents) > 999999999999) {
                throw ValidationException::withMessages(['items' => 'The quotation total is too large. Reduce the quantities or rates.']);
            }

            $retainedIds = [];
            foreach ($lines as $line) {
                $id = $line['id'] ?? null;
                unset($line['id']);
                if ($id) {
                    $item = $quote->items()->findOrFail($id);
                    $item->update($line);
                } else {
                    $item = $quote->items()->create($line);
                }
                $retainedIds[] = $item->id;
            }
            $quote->items()->whereNotIn('id', $retainedIds)->delete();
            $quote->update([
                'last_edited_by_user_id' => $request->user()->id,
                'subtotal' => $subtotalCents / 100,
                'discount' => $discountCents / 100,
                'tax_amount' => $taxCents / 100,
                'total' => $totalCents / 100,
                'status' => 'draft',
                'sent_at' => null, 'viewed_at' => null, 'accepted_at' => null, 'rejected_at' => null,
            ]);
        });

        return redirect()->route('admin.quotes.show', $quote)->with('success', 'Quotation services and totals saved as a draft. You can now email the revised quotation.');
    }

    public function updateContacts(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate([
            'contacts' => ['nullable', 'array', 'max:10'],
            'contacts.*' => ['array:name,role,phone,email'],
            'contacts.*.name' => ['required', 'string', 'max:120'],
            'contacts.*.role' => ['nullable', 'string', 'max:120'],
            'contacts.*.phone' => ['nullable', 'required_without:contacts.*.email', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'required_without:contacts.*.phone', 'email', 'max:160'],
        ]);

        $contacts = collect($data['contacts'] ?? [])->map(fn (array $contact): array => [
            'name' => trim($contact['name']),
            'role' => filled($contact['role'] ?? null) ? trim($contact['role']) : null,
            'phone' => filled($contact['phone'] ?? null) ? trim($contact['phone']) : null,
            'email' => filled($contact['email'] ?? null) ? strtolower(trim($contact['email'])) : null,
        ])->values()->all();

        $quote->update([
            'contact_people' => $contacts ?: null,
            'last_edited_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Quotation contacts updated.');
    }

    public function updateSiteVisit(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate([
            'site_visit_id' => [
                'nullable', 'integer',
                Rule::exists('site_visit_requests', 'id')->where('customer_id', $quote->customer_id),
            ],
        ]);
        $siteVisitId = filled($data['site_visit_id'] ?? null) ? (int) $data['site_visit_id'] : null;

        DB::transaction(function () use ($quote, $siteVisitId, $request): void {
            $lockedQuote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            $previousSiteVisitId = $lockedQuote->site_visit_request_id;

            $lockedQuote->update([
                'site_visit_request_id' => $siteVisitId,
                'last_edited_by_user_id' => $request->user()->id,
            ]);

            if ($previousSiteVisitId && $previousSiteVisitId !== $siteVisitId) {
                $previousSiteVisit = SiteVisitRequest::query()->lockForUpdate()->find($previousSiteVisitId);
                if ($previousSiteVisit?->quote_id === $lockedQuote->id) {
                    $previousSiteVisit->update([
                        'quote_id' => Quote::where('site_visit_request_id', $previousSiteVisitId)
                            ->where('id', '!=', $lockedQuote->id)
                            ->latest('id')
                            ->value('id'),
                    ]);
                }
            }

            if ($siteVisitId) {
                SiteVisitRequest::whereKey($siteVisitId)->update(['quote_id' => $lockedQuote->id]);
            }
        });

        return back()->with('success', $siteVisitId
            ? 'Quotation linked to the selected site-visit session.'
            : 'Quotation is now marked as a direct quotation.');
    }

    public function send(Request $request, Quote $quote, DocumentEmailRecipients $recipientParser): RedirectResponse
    {
        $quote->load(['customer', 'items.service']);
        $request->validate(['recipients' => ['nullable', 'string', 'max:2000']]);
        $recipients = $recipientParser->parse($request->string('recipients')->toString(), $quote->customer->email);

        try {
            foreach ($recipients as $recipient) {
                app(DocumentEmailHistory::class)->queue(
                    $quote, new QuoteMail($quote), 'quotation', $recipient,
                    $recipient === strtolower((string) $quote->customer->email) ? $quote->customer->name : null,
                );
            }
            $quote->update(['sent_by_user_id' => auth()->id()]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The quotation email could not be queued. Check the email history and server log before trying again.');
        }

        return back()->with('success', count($recipients).' quotation email'.(count($recipients) === 1 ? '' : 's').' queued for delivery.');
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
                'created_by_user_id' => auth()->id(),
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

        $booking = Booking::create([
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

        $booking->load(['customer', 'service', 'quote.items']);
        $confirmationQueued = false;
        if ($booking->customer->email) {
            $booking->update(['confirmation_sent_at' => now()]);
            Mail::to($booking->customer->email, $booking->customer->name)
                ->queue(new BookingConfirmationMail($booking));
            $confirmationQueued = true;
        }

        return redirect()->route('admin.bookings.index')->with('success', $confirmationQueued
            ? 'Booking created and confirmation email queued.'
            : 'Booking created. Add a customer email address to send a confirmation.');
    }
}
