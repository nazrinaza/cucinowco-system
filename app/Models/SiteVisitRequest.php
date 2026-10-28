<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SiteVisitRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'clean_types' => 'array',
            'assessment_data' => 'array',
            'assessment_completed_at' => 'datetime',
            'preferred_date' => 'date',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'contacted_at' => 'datetime',
            'completed_at' => 'datetime',
            'referral_landed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    public function getReferralLabelAttribute(): string
    {
        if ($this->referral_code === 'direct' || blank($this->referral_code)) {
            return 'Direct';
        }

        return $this->referralSource
            ? $this->referralSource->name.' · '.str($this->referralSource->channel)->replace('_', ' ')->title()
            : str($this->referral_code)->replace(['-', '_'], ' ')->title();
    }

    public function getCleanTypesLabelAttribute(): string
    {
        return $this->clean_types
            ? collect($this->clean_types)->map(function ($type) {
                if ($type === 'others' && filled($this->other_clean_type)) {
                    return 'Others: '.$this->other_clean_type;
                }

                return config('site_visits.clean_types.'.$type, $type);
            })->implode(', ')
            : ($this->service?->name ?? 'To be confirmed');
    }

    public function getSpaceLabelAttribute(): string
    {
        return config('site_visits.spaces.'.$this->space_type, (string) str($this->space_type)->replace('_', ' ')->title());
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SiteVisitPhoto::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function calendarSync(): HasOne
    {
        return $this->hasOne(SiteVisitCalendarSync::class);
    }

    public function assessmentCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessment_completed_by_user_id');
    }
}
