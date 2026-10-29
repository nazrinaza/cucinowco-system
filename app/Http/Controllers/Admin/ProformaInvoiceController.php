<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProformaInvoiceMail;
use App\Models\ProformaInvoice;
use App\Models\Quote;
use App\Support\DocumentEmailHistory;
use App\Support\DocumentEmailRecipients;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProformaInvoiceController extends Controller
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'cancelled'];

    public function index(Request $request): View
    {
        $proformaInvoices = ProformaInvoice::with(['customer', 'quote'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.proforma-invoices.index', ['proformaInvoices' => $proformaInvoices, 'statuses' => self::STATUSES]);
    }

    public function store(Quote $quote): RedirectResponse
    {
        if ($quote->proformaInvoice) {
            return redirect()->route('admin.proforma-invoices.show', $quote->proformaInvoice);
        }

        $proforma = DB::transaction(function () use ($quote): ProformaInvoice {
            $quote = Quote::with(['items', 'customer'])->lockForUpdate()->findOrFail($quote->id);
            if ($quote->proformaInvoice()->exists()) {
                return $quote->proformaInvoice;
            }

            $proforma = ProformaInvoice::create([
                'proforma_number' => ReferenceNumber::make('PI', ProformaInvoice::class, 'proforma_number'),
                'quote_id' => $quote->id, 'customer_id' => $quote->customer_id,
                'title' => $quote->display_title, 'status' => 'draft', 'issued_at' => today(),
                'valid_until' => $quote->valid_until, 'subtotal' => $quote->subtotal,
                'discount' => $quote->discount, 'tax_rate' => $quote->tax_rate,
                'tax_amount' => $quote->tax_amount, 'total' => $quote->total,
                'deposit_percentage' => $quote->deposit_percentage ?? 50,
                'sales_person_name' => $quote->sales_person_name,
                'sales_person_title' => $quote->sales_person_title,
                'sales_person_phone' => $quote->sales_person_phone,
                'created_by_user_id' => auth()->id(),
            ]);
            foreach ($quote->items as $item) {
                $proforma->items()->create($item->only(['description', 'quantity', 'unit', 'unit_price', 'amount', 'notes']));
            }

            return $proforma;
        });

        return redirect()->route('admin.proforma-invoices.show', $proforma)->with('success', 'Proforma invoice created from quotation.');
    }

    public function show(ProformaInvoice $proformaInvoice): View
    {
        $proformaInvoice->load(['customer', 'quote', 'items', 'createdBy', 'lastEditedBy', 'sentBy']);

        return view('admin.proforma-invoices.show', [
            'proformaInvoice' => $proformaInvoice, 'statuses' => self::STATUSES,
            'emailHistory' => app(DocumentEmailHistory::class)->forDocument($proformaInvoice),
        ]);
    }

    public function printable(ProformaInvoice $proformaInvoice): View
    {
        $proformaInvoice->load(['customer', 'items']);

        return view('emails.proforma-invoice', [
            'proformaInvoice' => $proformaInvoice, 'printMode' => true,
            'printBackUrl' => route('admin.proforma-invoices.show', $proformaInvoice),
        ]);
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'issued_at' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'deposit_percentage' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ]);
        $proformaInvoice->update([...$data, 'last_edited_by_user_id' => $request->user()->id]);

        return back()->with('success', 'Proforma invoice updated.');
    }

    public function send(Request $request, ProformaInvoice $proformaInvoice, DocumentEmailRecipients $recipientParser): RedirectResponse
    {
        $proformaInvoice->load(['customer', 'items']);
        $request->validate(['recipients' => ['nullable', 'string', 'max:2000']]);
        $recipients = $recipientParser->parse($request->string('recipients')->toString(), $proformaInvoice->customer->email);

        try {
            foreach ($recipients as $recipient) {
                app(DocumentEmailHistory::class)->queue(
                    $proformaInvoice, new ProformaInvoiceMail($proformaInvoice), 'proforma_invoice', $recipient,
                    $recipient === strtolower((string) $proformaInvoice->customer->email) ? $proformaInvoice->customer->name : null,
                );
            }
            $proformaInvoice->update(['sent_by_user_id' => $request->user()->id]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The proforma invoice email could not be queued. Check the email history and server log before trying again.');
        }

        return back()->with('success', count($recipients).' proforma invoice email'.(count($recipients) === 1 ? '' : 's').' queued for delivery.');
    }
}
