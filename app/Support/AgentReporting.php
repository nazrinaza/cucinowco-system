<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralVisit;
use App\Models\SiteVisitRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AgentReporting
{
    public function leads(Agent $agent): Builder
    {
        return SiteVisitRequest::where('referral_source_id', $agent->referral_source_id)
            ->whereNotNull('referral_source_id');
    }

    public function summary(Agent $agent): array
    {
        $quoteIds = $this->leads($agent)->whereNotNull('quote_id')->select('quote_id')->distinct();
        $quotes = Quote::whereIn('id', $quoteIds);
        $closed = (clone $quotes)->where(fn ($q) => $q->where('status', 'accepted')->orWhereHas('booking', fn ($b) => $b->whereIn('status', ['confirmed', 'in_progress', 'completed'])))->count();
        $leads = $this->leads($agent)->count();

        return [
            'visits' => $agent->referralSource?->visit_count ?? 0,
            'leads' => $leads,
            'closed' => $closed,
            'completed' => Booking::whereIn('quote_id', clone $quoteIds)->where('status', 'completed')->count(),
            'paid' => Invoice::whereIn('quote_id', clone $quoteIds)->where('status', 'paid')->where('balance', '<=', 0)->count(),
            'conversion' => $leads ? round($closed / $leads * 100, 1) : 0,
        ];
    }

    public function analytics(Agent $agent, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = ReferralVisit::where('referral_source_id', $agent->referral_source_id)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()]);
        $groups = [];
        foreach (['channel', 'device', 'origin_host', 'landing_path'] as $column) {
            $groups[$column] = (clone $query)->select($column)->selectRaw('COUNT(*) AS total')
                ->groupBy($column)->orderByDesc('total')->limit(15)->get();
        }
        $daily = (clone $query)->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupBy('day')->pluck('total', 'day');
        $timeline = collect();
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $timeline->push(['day' => $day->format('d M'), 'total' => (int) ($daily[$day->toDateString()] ?? 0)]);
        }

        return ['groups' => $groups, 'timeline' => $timeline, 'total' => (clone $query)->count()];
    }

    public function stage(SiteVisitRequest $lead): string
    {
        $quote = $lead->quote;
        $invoice = $quote?->invoice;
        $booking = $quote?->booking;
        if ($invoice?->status === 'paid' && (float) $invoice->balance <= 0) {
            return 'Paid';
        }
        if ($booking?->status === 'completed') {
            return 'Job completed';
        }
        if ($booking && in_array($booking->status, ['confirmed', 'in_progress'])) {
            return 'Closed deal';
        }
        if ($quote?->status === 'accepted') {
            return 'Closed deal';
        }
        if (in_array($lead->status, ['cancelled', 'rejected']) || in_array($quote?->status, ['rejected', 'expired'])) {
            return 'Not proceeding';
        }
        if ($quote) {
            return 'Quotation prepared';
        }

        return match ($lead->status) {
            'scheduled', 'confirmed' => 'Assessment scheduled',
            'completed', 'converted' => 'Assessment completed',
            'contacted' => 'HQ following up',
            default => 'New referral',
        };
    }
}
