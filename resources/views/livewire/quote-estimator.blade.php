<div class="estimator-card">
    @if($submitted)
        <div class="estimator-success"><span class="success-mark">&#10003;</span><p class="eyebrow">Quotation created</p><h3>{{ $reference }}</h3><p>The quotation has been saved as a draft. Review its scope and pricing before sending it to the customer.</p><a href="{{ route('admin.quotes.show', $quoteId) }}" class="button">Review quotation</a></div>
    @else
        <form wire:submit="submit" class="estimator-form estimator-quote-editor" data-quote-editor data-tax-rate="0">
            <div class="estimator-form-head"><p class="admin-kicker">Internal quotation tool</p><h2>Build quotation</h2><span>Complete this after reviewing the site requirements. Nothing is sent automatically.</span></div>

            @if($this->linkedSiteVisit)
                @php
                    $assessment = $this->linkedSiteVisit->assessment_data ?? [];
                    $tools = collect($assessment['tools'] ?? [])->map(fn ($value) => config('site_visits.tools.'.$value, str($value)->replace('_', ' ')->title()))->implode(', ');
                    $ppe = collect($assessment['ppe'] ?? [])->map(fn ($value) => config('site_visits.ppe.'.$value, str($value)->replace('_', ' ')->title()))->implode(', ');
                    $toolSummary = collect([$tools, $assessment['other_tools'] ?? null])->filter()->implode(' · ') ?: 'Not recorded';
                    $ppeSummary = collect([$ppe, !empty($assessment['safety_signage_required']) ? 'Safety signage / barricades required' : null, $assessment['other_ppe'] ?? null])->filter()->implode(' · ') ?: 'Not recorded';
                @endphp
                <section class="estimator-assessment" aria-labelledby="estimator-assessment-title">
                    <div class="estimator-assessment-head">
                        <div><span>Site visit {{ $this->linkedSiteVisit->reference_number }}</span><h3 id="estimator-assessment-title">Site Assessment Checklist</h3></div>
                        <a href="{{ route('admin.site-visits.show', $this->linkedSiteVisit) }}">Open full assessment &nearr;</a>
                    </div>
                    <div class="estimator-assessment-grid">
                        <p><span>Discovery source</span><strong>{{ config('site_visits.discovery_sources.'.($assessment['discovery_source'] ?? ''), $assessment['discovery_source_other'] ?? 'Not recorded') }}</strong></p>
                        <p><span>Estimated area</span><strong>{{ filled($assessment['site_size_sqft'] ?? null) ? number_format((float) $assessment['site_size_sqft']).' sq ft' : 'Not recorded' }}</strong></p>
                        <p><span>Floors</span><strong>{{ $assessment['floor_count'] ?? 'Not recorded' }}</strong></p>
                        <p><span>Premises type</span><strong>{{ config('site_visits.premises_types.'.($assessment['premises_type'] ?? ''), $assessment['premises_type_other'] ?? 'Not recorded') }}</strong></p>
                        <p><span>Expected duration</span><strong>{{ filled($assessment['expected_duration_hours'] ?? null) ? $assessment['expected_duration_hours'].' hours' : 'Not recorded' }}</strong></p>
                        <p><span>Forklift</span><strong>{{ !empty($assessment['forklift_required']) ? 'Required' : 'Not required' }}</strong></p>
                        <p class="span-two"><span>Machinery / tools</span><strong>{{ $toolSummary }}</strong></p>
                        <p class="span-two"><span>PPE &amp; safety</span><strong>{{ $ppeSummary }}</strong></p>
                        <p class="span-two"><span>Access / site restrictions</span><strong>{{ $assessment['access_notes'] ?? 'Not recorded' }}</strong></p>
                        <p class="span-two"><span>Recommended cleaning approach</span><strong>{{ $assessment['assessment_notes'] ?? 'Not recorded' }}</strong></p>
                    </div>
                    <p class="estimator-assessment-status">{{ $this->linkedSiteVisit->assessment_completed_at ? 'Completed by '.($this->linkedSiteVisit->assessmentCompletedBy?->name ?? 'team member').' on '.$this->linkedSiteVisit->assessment_completed_at->format('d M Y, g:i A') : 'Assessment is still a draft. Confirm the checklist before sending the quotation.' }}</p>
                </section>
            @endif

            <div class="form-grid two"><label><span>Primary service</span><select wire:model="serviceId" wire:change="applySelectedService">@foreach($this->services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>@error('serviceId')<small>{{ $message }}</small>@enderror</label><label><span>Space type</span><select wire:model="propertyType"><option value="office">Office</option><option value="hall">Grand hall / venue</option><option value="other">Other commercial space</option></select></label></div>
            <div class="form-grid two"><label><span>Approximate size</span><select wire:model="sizeBand"><option value="under_1000">Under 1,000 sq ft</option><option value="1000_2000">1,000 - 2,000 sq ft</option><option value="2000_5000">2,000 - 5,000 sq ft</option><option value="over_5000">Over 5,000 sq ft</option></select></label><label><span>Frequency</span><select wire:model="frequency"><option value="one_off">One-off</option><option value="weekly">Weekly</option><option value="fortnightly">Every 2 weeks</option><option value="monthly">Monthly</option></select></label></div>

            <section class="estimator-services" aria-labelledby="estimator-services-title">
                <div class="estimator-section-head"><div><p class="admin-kicker">Pricing &amp; scope</p><h3 id="estimator-services-title">Services and add-ons</h3></div><button type="button" class="admin-button secondary" wire:click="addItem" @disabled(count($items) >= 100)>Add service line</button></div>
                @error('items')<p class="estimator-field-error">{{ $message }}</p>@enderror
                <div class="estimator-service-lines" data-quote-lines>
                    @foreach($items as $index => $item)
                        <fieldset class="quote-edit-line" data-quote-line wire:key="estimate-item-{{ $index }}">
                            <legend>Service line {{ $index + 1 }}</legend>
                            <label class="quote-line-description">Service / add-on description<input wire:model="items.{{ $index }}.description" maxlength="255" required placeholder="e.g. Carpet cleaning" data-line-description>@error("items.$index.description")<small>{{ $message }}</small>@enderror</label>
                            <label>Quantity<input type="number" wire:model.live.debounce.300ms="items.{{ $index }}.quantity" min="0.01" max="10000" step="0.01" required data-line-quantity>@error("items.$index.quantity")<small>{{ $message }}</small>@enderror</label>
                            <label>Unit<input wire:model="items.{{ $index }}.unit" maxlength="30" required placeholder="job">@error("items.$index.unit")<small>{{ $message }}</small>@enderror</label>
                            <label>Rate (RM)<input type="number" wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" min="0" max="1000000" step="0.01" required data-line-rate>@error("items.$index.unit_price")<small>{{ $message }}</small>@enderror</label>
                            <div class="quote-line-amount">Amount <output data-line-amount>RM {{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2) }}</output></div>
                            <div class="quote-line-notes quote-rich-field" data-quote-rich-editor>
                                <span>Customer-facing scope details (optional)</span>
                                <div class="quote-rich-toolbar" role="toolbar" aria-label="Scope details formatting">
                                    <button type="button" class="quote-rich-tool" data-quote-rich-command="bold" title="Bold"><strong>B</strong></button>
                                    <button type="button" class="quote-rich-tool" data-quote-rich-command="italic" title="Italic"><em>I</em></button>
                                    <button type="button" class="quote-rich-tool" data-quote-rich-command="insertUnorderedList" title="Bullet list">&#8226; List</button>
                                    <button type="button" class="quote-rich-tool" data-quote-rich-hr title="Divider">&#8212; Divider</button>
                                </div>
                                <div class="quote-rich-canvas" contenteditable="true" role="textbox" aria-multiline="true" data-quote-rich-canvas>{!! app(\App\Support\QuotationHtmlSanitizer::class)->sanitize($item['notes'] ?? '') !!}</div>
                                <textarea wire:model="items.{{ $index }}.notes" maxlength="3000" hidden data-quote-rich-source>{{ $item['notes'] ?? '' }}</textarea>
                                <small>Only bold, italic, bullets and dividers are kept for quotation, PDF and email compatibility.</small>
                                @error("items.$index.notes")<small>{{ $message }}</small>@enderror
                            </div>
                            <button type="button" class="admin-button secondary" wire:click="removeItem({{ $index }})" @disabled(count($items) <= 1)>Remove line</button>
                        </fieldset>
                    @endforeach
                </div>
                <div class="estimator-pricing-summary">
                    <label><span>Discount (RM)</span><input type="number" wire:model.live.debounce.300ms="discount" min="0" max="9999999999.99" step="0.01" required data-quote-discount>@error('discount')<small>{{ $message }}</small>@enderror</label>
                    <div class="quote-editor-totals" aria-live="polite">
                        <span>Subtotal <strong data-quote-subtotal>RM {{ number_format($this->subtotal, 2) }}</strong></span>
                        <span>Grand total <strong data-quote-total>RM {{ number_format($this->total, 2) }}</strong></span>
                    </div>
                    <p class="quote-editor-help" data-quote-editor-message role="status"></p>
                </div>
            </section>

            <div class="form-grid two"><label><span>Preferred cleaning date</span><input type="date" wire:model="preferredDate" min="{{ now()->format('Y-m-d') }}">@error('preferredDate')<small>{{ $message }}</small>@enderror</label><label><span>Preferred cleaning time</span><select wire:model="preferredTimeSlot"><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="evening">Evening</option><option value="flexible">Flexible</option></select></label></div>
            <div class="form-grid two"><label><span>Customer name</span><input type="text" wire:model="name" autocomplete="name" placeholder="Full name">@error('name')<small>{{ $message }}</small>@enderror</label><label><span>Company <em>optional</em></span><input type="text" wire:model="companyName" autocomplete="organization" placeholder="Company name"></label></div>
            <label><span>Quotation title</span><input type="text" wire:model="documentTitle" placeholder="RE: QUOTATION FOR INITIAL CLEANING SERVICE AT COMPANY NAME">@error('documentTitle')<small>{{ $message }}</small>@enderror</label>
            <div class="form-grid two"><label><span>Deposit required (%)</span><input type="number" wire:model="depositPercentage" min="0" max="100" step="0.01">@error('depositPercentage')<small>{{ $message }}</small>@enderror</label><label><span>Sales person</span><select wire:model="salesPersonId"><option value="">No sales person</option>@foreach($this->salesPeople as $person)<option value="{{ $person->id }}">{{ $person->sales_signature_name ?: $person->name }}</option>@endforeach</select></label></div>
            <div class="form-grid two"><label><span>Mobile number</span><input type="tel" wire:model="phone" autocomplete="tel" placeholder="01X-XXXXXXX">@error('phone')<small>{{ $message }}</small>@enderror</label><label><span>Email <em>optional</em></span><input type="email" wire:model="email" autocomplete="email" placeholder="you@company.com">@error('email')<small>{{ $message }}</small>@enderror</label></div>
            <label><span>Service address</span><textarea wire:model="address" rows="2" placeholder="Unit, building and street"></textarea>@error('address')<small>{{ $message }}</small>@enderror</label>
            <div class="form-grid three"><label><span>Postcode</span><input type="text" wire:model="postcode" inputmode="numeric"></label><label><span>City</span><input type="text" wire:model="city"></label><label><span>State</span><input type="text" wire:model="state"></label></div>
            <label><span>Internal notes <em>optional</em></span><textarea wire:model="notes" rows="3" placeholder="Condition, access or other details for the internal team"></textarea></label>
            <button type="submit" class="button estimator-submit" wire:loading.attr="disabled"><span wire:loading.remove>Create draft quotation</span><span wire:loading>Saving...</span><small>Internal only</small></button>
            <p class="privacy-note">Review and send the quotation from the Quotes page after saving.</p>
        </form>
    @endif
</div>
