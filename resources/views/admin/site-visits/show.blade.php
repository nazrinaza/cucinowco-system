<x-layouts.admin title="Site visit {{ $siteVisit->reference_number }}" heading="{{ $siteVisit->reference_number }}">
    <x-slot:actions><div class="action-row">@can('manage-documents')<a href="{{ route('admin.quotes.create', ['site_visit' => $siteVisit->id]) }}" class="admin-button">{{ $siteVisit->quotes->isEmpty() ? 'Create estimate' : 'Create another quotation' }}</a>@endcan<a href="{{ route('admin.site-visits.index') }}" class="admin-button secondary">All requests</a></div></x-slot:actions>
    <div class="detail-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Public request</p><h2>{{ $siteVisit->clean_types_label }}</h2></div><span class="status status-{{ $siteVisit->status }}">{{ ucfirst($siteVisit->status) }}</span></div>
            <dl class="detail-list">
                <div><dt>Customer</dt><dd>{{ $siteVisit->customer->name }}<br>{{ $siteVisit->customer->company_name }}</dd></div>
                <div><dt>Contact</dt><dd>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</dd></div>
                <div><dt>Lead source</dt><dd><x-lead-source :label="$siteVisit->referral_label" /><br><small>{{ $siteVisit->referral_code ?: 'direct' }}@if($siteVisit->referral_landed_at) · {{ $siteVisit->referral_landed_at->format('d M Y, g:i A') }}@endif</small></dd></div>
                <div><dt>Submission IP</dt><dd>{{ $siteVisit->client_ip ?: 'Not captured' }}</dd></div>
                <div><dt>Space</dt><dd>{{ $siteVisit->space_label }}</dd></div>
                <div><dt>Preferred visit</dt><dd>{{ $siteVisit->preferred_date?->format('d M Y') }} &middot; {{ str($siteVisit->preferred_time_slot)->title() }}</dd></div>
                <div><dt>Site address</dt><dd>{{ $siteVisit->site_address ?: 'To be collected when confirming the visit.' }}<br>{{ $siteVisit->postcode }}</dd></div>
                <div><dt>Customer notes</dt><dd>{{ $siteVisit->customer_notes ?: 'No additional notes.' }}</dd></div>
            </dl>
        </section>
        <aside class="detail-side">
            <section class="admin-card"><div class="card-head"><div><p>Workflow</p><h2>Request status</h2></div></div><form method="post" action="{{ route('admin.site-visits.update', $siteVisit) }}" class="admin-form">@csrf @method('patch')
                <label><span>Status</span><select name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($siteVisit->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label><span>Assigned team</span><select name="staff_id"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((int) old('staff_id', $siteVisit->staff_id) === $member->id)>{{ $member->name }}</option>@endforeach</select></label>
                <label><span>Confirmed start</span><input type="datetime-local" name="scheduled_start" value="{{ old('scheduled_start', $siteVisit->scheduled_start?->format('Y-m-d\TH:i')) }}"><small>Required when the appointment is scheduled.</small></label>
                <label><span>Confirmed end</span><input type="datetime-local" name="scheduled_end" value="{{ old('scheduled_end', $siteVisit->scheduled_end?->format('Y-m-d\TH:i')) }}"><small>Required when the appointment is scheduled.</small></label>
                @if(in_array('others', $siteVisit->clean_types ?? [], true))<label><span>Specify other clean type</span><input name="other_clean_type" value="{{ old('other_clean_type', $siteVisit->other_clean_type) }}" maxlength="180" placeholder="e.g. High-level dust removal" required>@error('other_clean_type')<small>{{ $message }}</small>@enderror</label>@endif
                <label><span>Internal notes</span><textarea name="internal_notes" rows="5">{{ $siteVisit->internal_notes }}</textarea></label><button class="admin-button" type="submit">Save changes</button>
            </form>
            @php
                $siteVisitCalendarStatus = $siteVisit->calendarSync?->status ?? 'not_required';
            @endphp
            <div class="site-visit-calendar-sync"><span class="calendar-sync-status calendar-sync-{{ str($siteVisitCalendarStatus)->replace('_', '-') }}">Calendar: {{ str($siteVisitCalendarStatus)->replace('_', ' ')->title() }}</span>
                @can('manage-users')@if(in_array($siteVisitCalendarStatus, ['failed', 'not_connected'], true))<form method="post" action="{{ route('admin.site-visits.calendar.retry', $siteVisit) }}">@csrf<button class="admin-button secondary" type="submit">Retry calendar sync</button></form>@endif @endcan
                @if($siteVisit->calendarSync?->last_error)<small class="calendar-sync-error">{{ $siteVisit->calendarSync->last_error }}</small>@endif
            </div></section>
            <section class="admin-card"><div class="card-head"><div><p>Contact</p><h2>{{ $siteVisit->customer->name }}</h2></div></div><p>{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email ?: 'No email supplied' }}</p><a class="admin-button secondary full" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/','',$siteVisit->customer->phone) }}?text={{ urlencode('Hi '.$siteVisit->customer->name.', this is CuciNow regarding your free site visit '.$siteVisit->reference_number.'.') }}">Open WhatsApp</a></section>
        </aside>
    </div>
    <section class="admin-card site-visit-quotations">
        <div class="card-head"><div><p>Commercial history</p><h2>Quotations for this site visit</h2></div><span>{{ $siteVisit->quotes->count() }} total</span></div>
        @if($siteVisit->quotes->isEmpty())
            <p class="site-photo-empty">No quotation has been prepared yet.</p>
        @else
            <div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Created</th><th>Status</th><th>Total</th><th>Next document</th></tr></thead><tbody>
                @foreach($siteVisit->quotes as $quotation)
                    <tr>
                        <td><a href="{{ route('admin.quotes.show', $quotation) }}"><strong>{{ $quotation->quote_number }}</strong></a></td>
                        <td>{{ $quotation->created_at?->format('d M Y, g:i A') }}</td>
                        <td><span class="status status-{{ $quotation->status }}">{{ ucfirst($quotation->status) }}</span></td>
                        <td>RM {{ number_format($quotation->total, 2) }}</td>
                        <td>@if($quotation->invoice)<a href="{{ route('admin.invoices.show', $quotation->invoice) }}">Invoice {{ $quotation->invoice->invoice_number }}</a>@elseif($quotation->booking)Booking {{ $quotation->booking->booking_number }}@else<span class="muted">Not converted</span>@endif</td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
    @include('admin.site-visits._assessment')
    @include('admin.site-visits._gallery')
</x-layouts.admin>
