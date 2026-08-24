<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::with('customer')->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('admin.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['customer', 'quote', 'items', 'payments']);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'])], 'due_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $invoice->update($data);

        return back()->with('success', 'Invoice updated.');
    }

    public function payment(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->balance],
            'method' => ['required', Rule::in(['fpx', 'ewallet', 'card', 'bank_transfer', 'cash'])],
            'paid_at' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $invoice->payments()->create([...$data, 'payment_number' => ReferenceNumber::make('PAY', Payment::class, 'payment_number'), 'status' => 'completed']);
        $paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
        $balance = max(0, (float) $invoice->total - $paid);
        $invoice->update(['amount_paid' => $paid, 'balance' => $balance, 'status' => $balance <= 0 ? 'paid' : 'partial']);

        return back()->with('success', 'Payment recorded.');
    }
}
