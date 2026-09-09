<fieldset class="quote-edit-line" data-quote-line>
    <legend>Service line</legend>
    @if(!empty($line['id']))<input type="hidden" name="items[{{ $index }}][id]" value="{{ $line['id'] }}">@endif
    <label class="quote-line-description">Service / add-on description<input name="items[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" maxlength="255" required placeholder="e.g. Carpet cleaning" data-line-description></label>
    <label>Quantity<input type="number" name="items[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" min="0.01" max="10000" step="0.01" required data-line-quantity></label>
    <label>Unit<input name="items[{{ $index }}][unit]" value="{{ $line['unit'] ?? 'job' }}" maxlength="30" required></label>
    <label>Rate (RM)<input type="number" name="items[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '0.00' }}" min="0" max="1000000" step="0.01" required data-line-rate></label>
    <div class="quote-line-amount">Amount <output data-line-amount>RM {{ number_format((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2) }}</output></div>
    <label class="quote-line-notes">Line notes (optional)<textarea name="items[{{ $index }}][notes]" rows="2" maxlength="3000">{{ $line['notes'] ?? '' }}</textarea></label>
    <button type="button" class="admin-button secondary" data-remove-quote-line>Remove line</button>
</fieldset>
