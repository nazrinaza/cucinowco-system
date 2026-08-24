<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date', 'valid_until' => 'date', 'sent_at' => 'datetime',
            'viewed_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }
}
