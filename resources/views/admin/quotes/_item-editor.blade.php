@if($quote->invoice || $quote->booking)
    <p class="quote-edit-notice">Service pricing is locked because this quotation has an invoice or booking. Create a separate quotation for additional work.</p>
@else
    <details class="admin-card quote-item-editor" @if(old('items')) open @endif>
        <summary>Edit quotation &amp; add-on services <span>Change service lines and pricing</span></summary>
        <form method="post" action="{{ route('admin.quotes.items.update', $quote) }}" data-quote-editor data-tax-rate="{{ $quote->tax_rate }}">
            @csrf
            @method('patch')
            <p class="quote-editor-help">For a fixed-price add-on, use quantity 1 and enter its amount in Rate (RM). Saving returns this quotation to Draft; email it again when ready.</p>
            <div data-quote-lines>
                @foreach(old('items', $quote->items->map->only(['id', 'description', 'notes', 'quantity', 'unit', 'unit_price'])->toArray()) as $index => $line)
                    @include('admin.quotes._item-row', ['index' => $index, 'line' => $line])
                @endforeach
            </div>
            <template data-quote-line-template>
                @include('admin.quotes._item-row', ['index' => '__INDEX__', 'line' => ['quantity' => 1, 'unit' => 'job', 'unit_price' => '0.00']])
            </template>
            <div class="quote-editor-controls">
                <button type="button" class="admin-button secondary" data-add-quote-line>Add service line</button>
                <label>Discount (RM)<input type="number" name="discount" value="{{ old('discount', $quote->discount) }}" min="0" max="9999999999.99" step="0.01" required data-quote-discount></label>
            </div>
            <div class="quote-editor-totals" aria-live="polite">
                <span>Subtotal <strong data-quote-subtotal>RM {{ number_format($quote->subtotal, 2) }}</strong></span>
                <span>Tax ({{ number_format($quote->tax_rate, 2) }}%) <strong data-quote-tax>RM {{ number_format($quote->tax_amount, 2) }}</strong></span>
                <span>Total <strong data-quote-total>RM {{ number_format($quote->total, 2) }}</strong></span>
            </div>
            <p class="quote-editor-help" data-quote-editor-message role="status"></p>
            <button type="submit" class="admin-button">Save quotation services</button>
        </form>
    </details>
@endif
