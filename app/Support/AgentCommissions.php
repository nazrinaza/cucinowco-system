<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\CommissionPlan;
use App\Models\Invoice;
use App\Models\SiteVisitRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a fully paid invoice into commission ledger rows.
 *
 * Money is handled as integer sen so RM 0.01 rounding never drifts. The
 * rate and basis are snapshotted on each row: later plan changes do not
 * rewrite commissions that were already earned.
 */
class AgentCommissions
{
    /** @return Collection<int, AgentCommission> rows created by this call */
    public function recordFor(Invoice $invoice): Collection
    {
        $created = collect();
        if ($invoice->status !== 'paid' || (float) $invoice->balance > 0) {
            return $created;
        }

        $agent = $this->agentFor($invoice);
        if (! $agent) {
            return $created;
        }
        $plan = $agent->effectiveCommissionPlan();
        $basis = $plan?->basis ?? 'net_sales';
        $basisSen = $this->basisSen($invoice, $basis);

        $direct = $this->record($invoice, $agent, 'direct', $basis, $basisSen, $agent->effectiveCommissionRate());
        $direct && $created->push($direct);

        $introducer = $agent->introducerSource?->agent;
        if ($introducer && $introducer->id !== $agent->id && $plan) {
            $override = $this->record($invoice, $introducer, 'introducer', $basis, $basisSen, (string) $plan->introducer_rate_percent);
            $override && $created->push($override);
        }

        return $created;
    }

    public function agentFor(Invoice $invoice): ?Agent
    {
        $quote = $invoice->quote;
        if (! $quote) {
            return null;
        }
        $lead = $quote->siteVisit ?? SiteVisitRequest::where('quote_id', $quote->id)->first();
        if (! $lead?->referral_source_id) {
            return null;
        }

        return Agent::where('referral_source_id', $lead->referral_source_id)->first();
    }

    public function basisSen(Invoice $invoice, string $basis): int
    {
        return match ($basis) {
            'total' => self::toSen($invoice->total),
            default => max(0, self::toSen($invoice->subtotal) - self::toSen($invoice->discount)),
        };
    }

    /**
     * Commission for one invoice, in sen.
     *
     * @param  int  $basisSen  amount the rate applies to, e.g. RM 1,234.56 = 123456
     * @param  int  $rateBasisPoints  rate in hundredths of a percent, e.g. 7.50% = 750
     */
    public function commissionSen(int $basisSen, int $rateBasisPoints): int
    {
        // Round DOWN to whole sen; the caller puts the cut-off fraction into the company rounding reserve.
        return intdiv($basisSen * $rateBasisPoints, 10000);
    }

    public static function toSen(mixed $amount): int
    {
        [$ringgit, $sen] = array_pad(explode('.', number_format((float) $amount, 2, '.', '')), 2, '0');
        $negative = str_starts_with($ringgit, '-');

        return ($negative ? -1 : 1) * (abs((int) $ringgit) * 100 + (int) str_pad($sen, 2, '0'));
    }

    public static function fromSen(int $sen): string
    {
        return number_format($sen / 100, 2, '.', '');
    }

    private function record(Invoice $invoice, Agent $agent, string $type, string $basis, int $basisSen, string $rate): ?AgentCommission
    {
        $rateBp = self::toSen($rate);
        if ($rateBp <= 0 || $basisSen <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $agent, $type, $basis, $basisSen, $rate, $rateBp) {
            if (AgentCommission::where('invoice_id', $invoice->id)->where('type', $type)->lockForUpdate()->exists()) {
                return null;
            }

            $amountSen = $this->commissionSen($basisSen, $rateBp);
            // Exact commission is basisSen × rateBp / 10000 sen. Whatever the agent's rounded
            // amount leaves behind (in millionths of a ringgit) goes to the rounding reserve.
            $remainderMicro = $basisSen * $rateBp - $amountSen * 10000;

            return AgentCommission::create([
                'agent_id' => $agent->id, 'invoice_id' => $invoice->id, 'type' => $type, 'basis' => $basis,
                'basis_amount' => self::fromSen($basisSen), 'rate_percent' => $rate,
                'amount' => self::fromSen($amountSen), 'status' => 'pending',
                'rounding_remainder' => number_format($remainderMicro / 1_000_000, 6, '.', ''),
            ]);
        });
    }

    /** @return array{pending: string, approved: string, paid: string, lifetime: string} */
    public function earnings(Agent $agent): array
    {
        $sums = AgentCommission::where('agent_id', $agent->id)->earning()
            ->selectRaw('status, SUM(amount) AS total')->groupBy('status')->pluck('total', 'status');
        $lifetime = $sums->sum(fn ($total) => self::toSen($total));

        return [
            'pending' => self::fromSen(self::toSen($sums['pending'] ?? 0)),
            'approved' => self::fromSen(self::toSen($sums['approved'] ?? 0)),
            'paid' => self::fromSen(self::toSen($sums['paid'] ?? 0)),
            'lifetime' => self::fromSen($lifetime),
        ];
    }

    /** Company rounding reserve in RM (6 decimals): sum of sen fractions kept back from non-void commissions. */
    public function roundingReserve(): string
    {
        return number_format((float) AgentCommission::earning()->sum('rounding_remainder'), 6, '.', '');
    }

    public static function planIsConfigured(): bool
    {
        return CommissionPlan::where('rate_percent', '>', 0)->exists()
            || Agent::where('commission_rate_override', '>', 0)->exists();
    }
}
