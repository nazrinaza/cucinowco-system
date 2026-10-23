<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReferralSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'visit_count' => 'integer',
            'lead_count' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function siteVisitRequests(): HasMany
    {
        return $this->hasMany(SiteVisitRequest::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function scopeTrackable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereDoesntHave('agent')
                    ->orWhereHas('agent', fn (Builder $agentQuery) => $agentQuery
                        ->where('status', 'approved')
                        ->whereNotNull('email_verified_at'));
            });
    }

    public function getTrackingUrlAttribute(): string
    {
        return rtrim(config('agents.public_url'), '/').'/?'.http_build_query(['ref' => $this->code]);
    }
}
