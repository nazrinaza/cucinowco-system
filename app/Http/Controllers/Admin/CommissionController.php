<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\AgentCommission;
use App\Models\CommissionPlan;
use App\Models\Invoice;
use App\Support\AgentCommissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionController extends Controller
{
    /** Allowed ledger moves. Paid rows are final; correct them with a manual adjustment instead. */
    private const TRANSITIONS = [
        'pending' => ['approved', 'void'],
        'approved' => ['paid', 'pending', 'void'],
        'void' => ['pending'],
        'paid' => [],
    ];

    public function index(Request $request, AgentCommissions $commissions): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(AgentCommission::STATUSES)],
            'agent' => ['nullable', 'integer', 'exists:agents,id'],
        ]);
        $ledger = $this->filtered($filters);
        $totals = AgentCommission::selectRaw('status, COUNT(*) AS rows_count, SUM(amount) AS total')->groupBy('status')->get()->keyBy('status');

        return view('admin.commissions.index', [
            'plans' => CommissionPlan::withCount('agents')->orderByDesc('is_default')->orderBy('name')->get(),
            'commissions' => $ledger->with(['agent', 'invoice.customer'])->latest('id')->paginate(25)->withQueryString(),
            'totals' => $totals, 'filters' => $filters, 'reserve' => $commissions->roundingReserve(),
            'agents' => Agent::whereHas('commissions')->orderBy('name')->get(['id', 'name']),
            'bases' => CommissionPlan::BASES,
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $plan = CommissionPlan::create($this->validatePlan($request));
        $this->ensureSingleDefault($plan);

        return back()->with('success', "Commission plan \"{$plan->name}\" created.");
    }

    public function updatePlan(Request $request, CommissionPlan $plan): RedirectResponse
    {
        $plan->update($this->validatePlan($request));
        $this->ensureSingleDefault($plan);

        return back()->with('success', 'Commission plan updated. Earned commissions keep the rate recorded when they were earned.');
    }

    public function update(Request $request, AgentCommission $commission): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AgentCommission::STATUSES)],
            'payout_reference' => ['nullable', 'required_if:status,paid', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($commission, $data, $request) {
            $commission = AgentCommission::lockForUpdate()->findOrFail($commission->id);
            if ($data['status'] !== $commission->status && ! in_array($data['status'], self::TRANSITIONS[$commission->status], true)) {
                throw ValidationException::withMessages(['status' => "A {$commission->status} commission cannot be moved to {$data['status']}."]);
            }
            if ($data['status'] === 'paid' && $commission->status !== 'paid' && ! $commission->agent->hasPayoutDetails()) {
                throw ValidationException::withMessages(['status' => "{$commission->agent->name} has not added bank details yet, so this commission cannot be marked paid."]);
            }
            $commission->forceFill([
                'status' => $data['status'],
                'notes' => $data['notes'] ?? $commission->notes,
                'payout_reference' => $data['payout_reference'] ?? $commission->payout_reference,
                'approved_by_user_id' => $data['status'] === 'approved' ? $request->user()->id : $commission->approved_by_user_id,
                'approved_at' => $data['status'] === 'approved' ? now() : $commission->approved_at,
                'paid_at' => $data['status'] === 'paid' ? now() : ($data['status'] === 'pending' ? null : $commission->paid_at),
            ])->save();
            AgentActivity::create(['agent_id' => $commission->agent_id, 'user_id' => $request->user()->id, 'action' => 'commission.'.$data['status'], 'details' => ['commission_id' => $commission->id, 'amount' => (string) $commission->amount]]);
        });

        return back()->with('success', 'Commission marked '.$data['status'].'.');
    }

    /** Re-checks paid invoices, e.g. after HQ sets the first rate. Existing rows are never duplicated. */
    public function backfill(AgentCommissions $commissions): RedirectResponse
    {
        $created = 0;
        Invoice::where('status', 'paid')->where('balance', '<=', 0)->whereNotNull('quote_id')
            ->with('quote.siteVisit')->chunkById(100, function ($invoices) use ($commissions, &$created) {
                foreach ($invoices as $invoice) {
                    $created += $commissions->recordFor($invoice)->count();
                }
            });

        return back()->with('success', $created ? "{$created} commission row(s) added from paid invoices." : 'No new commissions found. Check that a plan rate is above 0%.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(AgentCommission::STATUSES)], 'agent' => ['nullable', 'integer']]);

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Agent', 'Agent email', 'Type', 'Invoice', 'Invoice paid', 'Basis', 'Basis amount (RM)', 'Rate %', 'Commission (RM)', 'Rounding reserve (RM)', 'Status', 'Payout reference', 'Paid at', 'Bank', 'Account name', 'Account number']);
            $this->filtered($filters)->with(['agent', 'invoice'])->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->id, $row->agent->name, $row->agent->email, $row->type, $row->invoice->invoice_number, $row->created_at->toDateString(), $row->basis, $row->basis_amount, $row->rate_percent, $row->amount, $row->rounding_remainder, $row->status, $row->payout_reference, $row->paid_at?->toDateString(),
                        $row->agent->payoutBankName(), $row->agent->payout_account_name,
                        // ="…" keeps leading zeros and stops Excel turning long numbers into 1.23E+11. Digits only, so no formula injection.
                        $row->agent->payout_account_number ? '="'.$row->agent->payout_account_number.'"' : null]);
                }
            });
            fclose($out);
        }, 'agent-commissions-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(array $filters)
    {
        return AgentCommission::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['agent'] ?? null, fn ($q, $agent) => $q->where('agent_id', $agent));
    }

    private function validatePlan(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:50', 'decimal:0,2'],
            'introducer_rate_percent' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
            'basis' => ['required', Rule::in(array_keys(CommissionPlan::BASES))],
            'is_default' => ['nullable', 'boolean'],
        ]);

        return [...$data, 'is_default' => (bool) ($data['is_default'] ?? false)];
    }

    private function ensureSingleDefault(CommissionPlan $plan): void
    {
        if ($plan->is_default) {
            CommissionPlan::whereKeyNot($plan->id)->update(['is_default' => false]);
        } elseif (! CommissionPlan::where('is_default', true)->exists()) {
            $plan->update(['is_default' => true]);
        }
    }
}
