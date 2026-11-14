<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    public const STATUSES = ['pending', 'approved', 'paid', 'void'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'basis_amount' => 'decimal:2', 'rate_percent' => 'decimal:2', 'amount' => 'decimal:2', 'rounding_remainder' => 'decimal:6',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function scopeEarning(Builder $query): Builder
    {
        return $query->where('status', '!=', 'void');
    }
}
