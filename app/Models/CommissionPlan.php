<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPlan extends Model
{
    public const BASES = ['net_sales' => 'Net sales (subtotal − discount, before tax)', 'total' => 'Invoice total (incl. tax)'];

    protected $fillable = ['name', 'rate_percent', 'introducer_rate_percent', 'basis', 'is_default'];

    protected function casts(): array
    {
        return ['rate_percent' => 'decimal:2', 'introducer_rate_percent' => 'decimal:2', 'is_default' => 'boolean'];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->oldest('id')->first();
    }
}
