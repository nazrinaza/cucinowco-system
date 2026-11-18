<x-layouts.admin title="Customer" heading="{{ $customer->name }}">
    <x-slot:actions><a class="admin-button" target="_blank" href="https://wa.me/{{ preg_replace('/\D/','',$customer->phone) }}">WhatsApp customer</a></x-slot:actions>
    <div class="detail-grid">
        <section class="admin-card"><div class="card-head"><div><p>Customer profile</p><h2>Contact & service details</h2></div></div><dl class="detail-list"><div><dt>Phone</dt><dd>{{ $customer->phone }}</dd></div><div><dt>Email</dt><dd>{{ $customer->email ?: 'Not supplied' }}</dd></div><div><dt>Lead source</dt><dd><x-lead-source :label="$customer->referral_label" /></dd></div><div><dt>Type</dt><dd>{{ ucfirst($customer->type) }}</dd></div><div><dt>Company</dt><dd>{{ $customer->company_name ?: 'Not supplied' }}</dd></div><div><dt>Address</dt><dd>{{ $customer->address }}<br>{{ $customer->postcode }} {{ $customer->city }}, {{ $customer->state }}</dd></div></dl></section>
        <section class="admin-card"><div class="card-head"><div><p>History</p><h2>Quotes</h2></div></div><div class="mini-list">@forelse($customer->quotes as $quote)<a href="{{ route('admin.quotes.show',$quote) }}"><span><strong>{{ $quote->quote_number }}</strong><small>{{ $quote->created_at->format('d M Y') }} · {{ $quote->referral_label }}</small></span><span class="status status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span></a>@empty<p class="empty-cell">No quote history.</p>@endforelse</div></section>
    </div>

    @can('manage-users')
        <section class="admin-card customer-cleanup">
            <div class="card-head"><div><p>Test-data cleanup</p><h2>Remove this customer and linked records</h2></div></div>
            <p class="customer-cleanup-warning">Use this only for testing records. This permanently removes the customer, site visits, photos, quotations, proforma invoices, bookings, invoices, payments, commissions and related email history.</p>
            <div class="customer-cleanup-summary">
                <p><strong>{{ $cleanupSummary['site_visits'] }}</strong><span>Site visits</span></p>
                <p><strong>{{ $cleanupSummary['quotes'] }}</strong><span>Quotations</span></p>
                <p><strong>{{ $cleanupSummary['bookings'] }}</strong><span>Bookings</span></p>
                <p><strong>{{ $cleanupSummary['invoices'] }}</strong><span>Invoices</span></p>
                <p><strong>{{ $cleanupSummary['payments'] }}</strong><span>Payments</span></p>
                <p><strong>RM {{ number_format($cleanupSummary['amount_paid'], 2) }}</strong><span>Dashboard revenue removed</span></p>
            </div>
            <form method="post" action="{{ route('admin.customers.test-data.destroy', $customer) }}" class="customer-cleanup-form">
                @csrf @method('delete')
                <label><span>Type <code>DELETE CUSTOMER {{ $customer->id }}</code></span><input name="confirmation" autocomplete="off" required></label>
                <label class="customer-cleanup-check"><input type="checkbox" name="confirm_test_data" value="1" required><span>I confirm this customer and every linked record are test data.</span></label>
                <button class="admin-button danger" type="submit">Permanently remove test data</button>
            </form>
        </section>
    @endcan
</x-layouts.admin>
