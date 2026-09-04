<x-layouts.admin title="Invoice {{ $invoice->invoice_number }}" heading="{{ $invoice->invoice_number }}">
    <x-slot:actions>
        <div class="action-row">
            @if($invoice->customer->email)
                <form method="post" action="{{ route('admin.invoices.send', $invoice) }}">@csrf<button class="admin-button" type="submit">Email invoice</button></form>
            @endif
            <button type="button" onclick="window.print()" class="admin-button secondary">Print / save PDF</button>
        </div>
    </x-slot:actions>
    <div class="detail-grid">
        <section class="admin-card document-card">
            <div class="document-head"><img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co"><div><span>Invoice</span><strong>{{ $invoice->invoice_number }}</strong><small>Issued {{ $invoice->issued_at?->format('d M Y') }} &middot; Due {{ $invoice->due_at?->format('d M Y') }}</small></div></div>
            <div class="document-parties"><div><span>Bill to</span><strong>{{ $invoice->customer->name }}</strong><p>{{ $invoice->customer->address }}<br>{{ $invoice->customer->email }}<br>{{ $invoice->customer->phone }}</p></div><div><span>From</span><strong>{{ config('company.legal_name') }}</strong><p>{{ config('company.address') }}<br>Reg. {{ config('company.registration_number') }}</p></div></div>
            <table class="document-table"><thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td><strong>{{ $item->description }}</strong></td><td>{{ number_format($item->quantity,2) }} {{ $item->unit }}</td><td>RM {{ number_format($item->unit_price,2) }}</td><td>RM {{ number_format($item->amount,2) }}</td></tr>@endforeach</tbody></table>
            <div class="totals-list">
                <p><span>Subtotal</span><strong>RM {{ number_format($invoice->subtotal,2) }}</strong></p>
                @if($invoice->discount>0)<p><span>Discount</span><strong>- RM {{ number_format($invoice->discount,2) }}</strong></p>@endif
                @if($invoice->tax_rate>0)<p><span>SST ({{ number_format($invoice->tax_rate,0) }}%)</span><strong>RM {{ number_format($invoice->tax_amount,2) }}</strong></p>@endif
                <p class="grand"><span>Total</span><strong>RM {{ number_format($invoice->total,2) }}</strong></p>
                <p><span>Paid</span><strong>RM {{ number_format($invoice->amount_paid,2) }}</strong></p>
                <p class="balance"><span>Balance due</span><strong>RM {{ number_format($invoice->balance,2) }}</strong></p>
            </div>
            <div class="document-notes"><strong>Payment terms</strong><p>{{ $invoice->payment_terms }}</p>@if($invoice->notes)<p>{{ $invoice->notes }}</p>@endif</div>
        </section>
        <aside class="detail-side">
            <section class="admin-card">
                <div class="card-head"><div><p>Invoice</p><h2>Status & due date</h2></div><span class="status status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span></div>
                <form class="admin-form" method="post" action="{{ route('admin.invoices.update',$invoice) }}">@csrf @method('patch')<label><span>Status</span><select name="status">@foreach(['draft','sent','partial','paid','overdue','cancelled'] as $status)<option value="{{ $status }}" @selected($invoice->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label><span>Due date</span><input type="date" name="due_at" value="{{ $invoice->due_at?->format('Y-m-d') }}"></label><label><span>Notes</span><textarea name="notes" rows="3">{{ $invoice->notes }}</textarea></label><button class="admin-button" type="submit">Save invoice</button></form>
            </section>
            @if($invoice->balance>0)
                <section class="admin-card">
                    <div class="card-head"><div><p>Payment</p><h2>Record payment</h2></div></div>
                    <form class="admin-form" method="post" action="{{ route('admin.invoices.payments',$invoice) }}">@csrf<label><span>Amount (RM)</span><input type="number" name="amount" step="0.01" min="0.01" max="{{ $invoice->balance }}" value="{{ $invoice->balance }}"></label><label><span>Method</span><select name="method"><option value="fpx">FPX</option><option value="ewallet">E-wallet</option><option value="card">Credit / debit card</option><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option></select></label><label><span>Paid at</span><input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label><label><span>Reference</span><input name="reference"></label><button class="admin-button" type="submit">Record payment</button></form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.admin>
