<x-layouts.admin title="Commissions" heading="Agent commissions">
    @php($sum = fn ($status) => number_format((float) ($totals[$status]->total ?? 0), 2))
    @php($count = fn ($status) => (int) ($totals[$status]->rows_count ?? 0))

    @if($errors->any())
        <div class="commission-errors" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="metric-grid">
        <article><span>Pending review</span><strong>RM {{ $sum('pending') }}</strong><small>{{ $count('pending') }} row(s) awaiting HQ approval</small></article>
        <article><span>Approved, unpaid</span><strong>RM {{ $sum('approved') }}</strong><small>{{ $count('approved') }} row(s) ready for payout</small></article>
        <article><span>Paid out</span><strong>RM {{ $sum('paid') }}</strong><small>{{ $count('paid') }} row(s) settled</small></article>
        <article class="metric-dark"><span>Company rounding reserve</span><strong>RM {{ number_format((float) $reserve, 4) }}</strong><small>Sen fractions kept back from commissions · exact RM {{ $reserve }} · void RM {{ $sum('void') }}</small></article>
    </div>

    <div class="commission-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Programme rules</p><h2>Commission plans</h2></div></div>
            <div class="commission-plans">
                @foreach($plans as $plan)
                    <form method="post" action="{{ route('admin.commissions.plans.update', $plan) }}" class="commission-plan-row">
                        @csrf @method('patch')
                        <label><span>Name</span><input name="name" value="{{ $plan->name }}" maxlength="80" required></label>
                        <label><span>Agent rate %</span><input name="rate_percent" type="number" step="0.01" min="0" max="50" value="{{ $plan->rate_percent }}" required></label>
                        <label><span>Introducer %</span><input name="introducer_rate_percent" type="number" step="0.01" min="0" max="20" value="{{ $plan->introducer_rate_percent }}" required></label>
                        <label><span>Basis</span><select name="basis">@foreach($bases as $value => $label)<option value="{{ $value }}" @selected($plan->basis === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="commission-check"><input type="checkbox" name="is_default" value="1" @checked($plan->is_default)><span>Default</span></label>
                        <button class="admin-button">Save</button>
                        <small>{{ $plan->agents_count }} agent(s) assigned directly{{ $plan->is_default ? ' · used by every agent without a plan' : '' }}</small>
                    </form>
                @endforeach
                <details class="commission-new">
                    <summary>+ Add another plan (e.g. Premium partner)</summary>
                    <form method="post" action="{{ route('admin.commissions.plans.store') }}" class="commission-plan-row">
                        @csrf
                        <label><span>Name</span><input name="name" maxlength="80" required></label>
                        <label><span>Agent rate %</span><input name="rate_percent" type="number" step="0.01" min="0" max="50" value="0" required></label>
                        <label><span>Introducer %</span><input name="introducer_rate_percent" type="number" step="0.01" min="0" max="20" value="0" required></label>
                        <label><span>Basis</span><select name="basis">@foreach($bases as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label class="commission-check"><input type="checkbox" name="is_default" value="1"><span>Default</span></label>
                        <button class="admin-button">Create</button>
                    </form>
                </details>
            </div>
        </section>
        <section class="admin-card referral-guide">
            <div class="card-head"><div><p>How it works</p><h2>From paid invoice to payout</h2></div></div>
            <ol>
                <li><span>01</span>An invoice from an agent-referred lead is <strong>fully paid</strong>.</li>
                <li><span>02</span>A <strong>pending</strong> row is created with the agent's rate at that moment.</li>
                <li><span>03</span>HQ approves it, then marks it <strong>paid</strong> with a payout reference.</li>
                <li><span>04</span>Changing a plan later never rewrites rows that were already earned.</li>
            </ol>
            <form method="post" action="{{ route('admin.commissions.backfill') }}" class="admin-form">
                @csrf
                <button class="admin-button">Scan paid invoices for missing commissions</button>
                <small class="muted">Use once after setting the first rate. Existing rows are never duplicated.</small>
            </form>
        </section>
    </div>

    <section class="admin-card" style="margin-top:18px">
        <div class="card-head">
            <div><p>Ledger</p><h2>Commission rows</h2></div>
            <a href="{{ route('admin.commissions.export', $filters) }}">Export CSV</a>
        </div>
        <form method="get" class="admin-filters commission-filters">
            <select name="status"><option value="">All statuses</option>@foreach(\App\Models\AgentCommission::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <select name="agent"><option value="">All agents</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((int) ($filters['agent'] ?? 0) === $agent->id)>{{ $agent->name }}</option>@endforeach</select>
            <button class="admin-button">Filter</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Agent</th><th>Invoice</th><th>Basis</th><th>Rate</th><th class="align-right">Commission</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($commissions as $row)
                    <tr>
                        <td><a href="{{ route('admin.agents.show', $row->agent) }}">{{ $row->agent->name }}</a><small>{{ $row->type === 'introducer' ? 'Introducer override' : 'Direct referral' }}</small></td>
                        <td><a href="{{ route('admin.invoices.show', $row->invoice) }}">{{ $row->invoice->invoice_number }}</a><small>{{ $row->invoice->customer?->name }}</small></td>
                        <td>RM {{ number_format((float) $row->basis_amount, 2) }}<small>{{ $row->basis === 'total' ? 'Invoice total' : 'Net sales' }}</small></td>
                        <td>{{ rtrim(rtrim($row->rate_percent, '0'), '.') }}%</td>
                        <td class="align-right"><strong>RM {{ number_format((float) $row->amount, 2) }}</strong>@if((float) $row->rounding_remainder != 0)<small>+ RM {{ rtrim($row->rounding_remainder, '0') }} to reserve</small>@endif</td>
                        <td><span class="status status-{{ $row->status === 'void' ? 'cancelled' : ($row->status === 'approved' ? 'confirmed' : $row->status) }}">{{ ucfirst($row->status) }}</span>@if($row->payout_reference)<small>{{ $row->payout_reference }}</small>@endif</td>
                        <td>
                            @if($row->status !== 'paid')
                                <form method="post" action="{{ route('admin.commissions.update', $row) }}" class="commission-action">
                                    @csrf @method('patch')
                                    @if($row->status === 'pending')
                                        <button name="status" value="approved" class="admin-button">Approve</button>
                                        <button name="status" value="void" class="commission-link">Void</button>
                                    @elseif($row->status === 'approved')
                                        @if($row->agent->hasPayoutDetails())
                                            <small class="commission-bank">{{ $row->agent->payoutBankName() }} · {{ $row->agent->payout_account_number }}<br>{{ $row->agent->payout_account_name }}</small>
                                            <input name="payout_reference" placeholder="Payout ref" maxlength="120" aria-label="Payout reference">
                                            <button name="status" value="paid" class="admin-button">Mark paid</button>
                                        @else
                                            <small class="commission-payout-missing">No bank details yet</small>
                                        @endif
                                        <button name="status" value="void" class="commission-link">Void</button>
                                    @else
                                        <button name="status" value="pending" class="commission-link">Restore</button>
                                    @endif
                                </form>
                            @else
                                <small>{{ $row->paid_at?->format('d M Y') }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell">Commissions appear here when an agent-referred invoice is fully paid and the plan rate is above 0%.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $commissions->links() }}</div>
    </section>
</x-layouts.admin>
