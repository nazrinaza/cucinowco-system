<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Agent programme rules HQ can change from Admin → Commissions.
 * Values are read once per request; there are only a handful of rows.
 */
class ProgrammeSettings
{
    public const DEFAULTS = ['payout_minimum' => '50.00', 'payout_days' => '10,28', 'customer_discount_percent' => '0'];

    private ?array $values = null;

    public function all(): array
    {
        if ($this->values === null) {
            try {
                $stored = DB::table('agent_programme_settings')->pluck('value', 'key')->all();
            } catch (QueryException $exception) {
                // The public site-visit form reads this; it must never break the homepage
                // (e.g. in the moment between a deploy and its migration).
                report($exception);
                $stored = [];
            }
            $this->values = [...self::DEFAULTS, ...$stored];
        }

        return $this->values;
    }

    public function payoutMinimum(): string
    {
        return number_format((float) $this->all()['payout_minimum'], 2, '.', '');
    }

    /** @return list<int> days of the month HQ pays out, e.g. [10, 28] */
    public function payoutDays(): array
    {
        $days = array_map('intval', array_filter(explode(',', (string) $this->all()['payout_days'])));
        $days = array_values(array_unique(array_filter($days, fn ($d) => $d >= 1 && $d <= 28)));
        sort($days);

        return $days ?: [10, 28];
    }

    public function nextPayoutDate(?CarbonImmutable $from = null): CarbonImmutable
    {
        $from = ($from ?? CarbonImmutable::today('Asia/Kuala_Lumpur'))->startOfDay();
        foreach ([0, 1] as $monthOffset) {
            $month = $from->startOfMonth()->addMonths($monthOffset);
            foreach ($this->payoutDays() as $day) {
                $date = $month->setDay($day);
                if ($date->greaterThanOrEqualTo($from)) {
                    return $date;
                }
            }
        }

        return $from->startOfMonth()->addMonths(2)->setDay($this->payoutDays()[0]);
    }

    public function customerDiscountPercent(): float
    {
        return max(0, min(50, (float) $this->all()['customer_discount_percent']));
    }

    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('agent_programme_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        $this->values = null;
    }
}
