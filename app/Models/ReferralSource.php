<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getTrackingUrlAttribute(): string
    {
        return url('/').'?'.http_build_query(['ref' => $this->code]);
    }
}
