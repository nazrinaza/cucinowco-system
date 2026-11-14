<x-layouts.admin title="Agent review" :heading="$agent->name">
    <div class="metric-grid">@foreach(['visits' => 'Referral visits', 'leads' => 'Leads', 'closed' => 'Closed deals', 'paid' => 'Paid invoices'] as $key => $label)<article><span>{{ $label }}</span><strong>{{ $summary[$key] }}</strong></article>@endforeach</div>
    <div class="detail-grid"><section class="admin-card"><div class="card-head"><div><p>Application details</p><h2>Partner profile</h2></div><span class="status">{{ ucfirst($agent->status) }}</span></div><dl class="detail-list"><div><dt>Email</dt><dd>{{ $agent->email }} · {{ $agent->email_verified_at ? 'Verified' : 'Awaiting verification' }}</dd></div><div><dt>Phone</dt><dd>{{ $agent->phone }}</dd></div><div><dt>Location</dt><dd>{{ $agent->location }}</dd></div><div><dt>Requested code</dt><dd>{{ $agent->requested_code }}</dd></div><div><dt>Introduced by</dt><dd>{{ $agent->introducer_name ?? 'Direct' }} · {{ $agent->introducer_code }} @if($agent->introducer_channel) ({{ ucfirst($agent->introducer_channel) }}) @endif</dd></div><div><dt>Joined</dt><dd>{{ $agent->created_at->format('d M Y, g:i A') }}</dd></div><div><dt>Referral URL</dt><dd>{{ $agent->referralSource?->tracking_url ?? 'Available after approval' }}</dd></div></dl>
        @if($agent->social_links)<div class="agent-admin-socials">@foreach(array_filter($agent->social_links) as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ucfirst($label) }} ↗</a>@endforeach</div>@endif
    </section><section class="admin-card"><div class="card-head"><div><p>HQ controls</p><h2>Review & access</h2></div></div><form method="post" class="admin-form" action="{{ route('admin.agents.update', $agent) }}">@csrf @method('patch')<label><span>Status</span><select name="status">@foreach(['pending', 'approved', 'suspended', 'rejected'] as $status)<option value="{{ $status }}" @selected($agent->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        @if(!$agent->referral_source_id)<label><span>Link an existing agent QR <em>optional</em></span><select name="referral_source_id"><option value="">Create the requested code on approval</option>@foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->name }} · {{ $source->code }}</option>@endforeach</select><small>Existing visits and leads remain attached to that source. Only unassigned agent codes can be linked.</small></label>@endif
        <label><span>Internal HQ notes</span><textarea name="hq_notes" rows="4">{{ old('hq_notes', $agent->hq_notes) }}</textarea></label><p class="muted">Approval requires email verification. Suspension pauses the referral link and portal access.</p><button class="admin-button">Save agent review</button></form></section></div>
    <div class="detail-grid" style="margin-top:24px">
        <section class="admin-card">
            <div class="card-head"><div><p>Earnings</p><h2>Commission ledger</h2></div><a href="{{ route('admin.commissions.index', ['agent' => $agent->id]) }}">Open full ledger</a></div>
            <div class="commission-earnings">
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid out', 'lifetime' => 'Lifetime'] as $key => $label)
                    <p><span>{{ $label }}</span><strong>RM {{ number_format((float) $earnings[$key], 2) }}</strong></p>
                @endforeach
            </div>
            <div class="mini-list">
                @forelse($commissions as $row)
                    <article><div><strong>{{ $row->invoice->invoice_number }} · RM {{ number_format((float) $row->amount, 2) }}</strong><small>{{ ucfirst($row->type) }} · {{ rtrim(rtrim($row->rate_percent, '0'), '.') }}% of RM {{ number_format((float) $row->basis_amount, 2) }}</small></div><span class="status">{{ ucfirst($row->status) }}</span></article>
                @empty
                    <p class="empty-cell">No commissions yet.</p>
                @endforelse
            </div>
        </section>
        <section class="admin-card">
            <div class="card-head"><div><p>HQ controls</p><h2>Commission settings</h2></div></div>
            <form method="post" class="admin-form" action="{{ route('admin.agents.commission', $agent) }}">
                @csrf @method('patch')
                <label><span>Plan</span><select name="commission_plan_id"><option value="">Use default plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($agent->commission_plan_id === $plan->id)>{{ $plan->name }} · {{ rtrim(rtrim($plan->rate_percent, '0'), '.') }}%{{ $plan->is_default ? ' (default)' : '' }}</option>@endforeach</select></label>
                <label><span>Personal rate % <em>optional override</em></span><input name="commission_rate_override" type="number" step="0.01" min="0" max="50" value="{{ old('commission_rate_override', $agent->commission_rate_override) }}" placeholder="Leave blank to use the plan rate"></label>
                <p class="muted">Effective rate now: <strong>{{ rtrim(rtrim($agent->effectiveCommissionRate(), '0'), '.') }}%</strong>. Changes apply to invoices paid from now on.</p>
                <button class="admin-button">Save commission settings</button>
            </form>
        </section>
    </div>
    <section class="admin-card" style="margin-top:24px"><div class="card-head"><div><p>Attributed enquiries</p><h2>Referred leads</h2></div></div><div class="mini-list">@forelse($leads as $lead)<a href="{{ route('admin.site-visits.show', $lead) }}"><strong>{{ $lead->reference_number }} · {{ $lead->customer->name }}</strong><span>{{ ucfirst($lead->status) }}</span></a>@empty<p class="empty-cell">No referred leads yet.</p>@endforelse</div><div class="pagination-wrap">{{ $leads->links() }}</div></section>
    <section class="admin-card" style="margin-top:24px"><div class="card-head"><div><p>Latest 30 changes</p><h2>Activity trail</h2></div></div><div class="mini-list">@foreach($activities as $activity)<div><strong>{{ $activity->action }}</strong><small>{{ $activity->created_at->format('d M Y, g:i A') }} · {{ $activity->user_id ? 'HQ user #'.$activity->user_id : 'Agent #'.$activity->agent_id }}@if($activity->details) · {{ json_encode($activity->details) }}@endif</small></div>@endforeach</div></section>
</x-layouts.admin>
