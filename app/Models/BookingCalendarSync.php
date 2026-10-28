<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCalendarSync extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
