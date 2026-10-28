<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'staff_id' => 'integer',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function calendarSync(): HasOne
    {
        return $this->hasOne(BookingCalendarSync::class);
    }

    public function getReferralLabelAttribute(): string
    {
        return $this->quote?->referral_label ?? 'Direct';
    }
}
