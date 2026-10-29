<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProformaInvoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date', 'valid_until' => 'date', 'sent_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'deposit_percentage' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(ProformaInvoiceItem::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function lastEditedBy(): BelongsTo { return $this->belongsTo(User::class, 'last_edited_by_user_id'); }
    public function sentBy(): BelongsTo { return $this->belongsTo(User::class, 'sent_by_user_id'); }

    public function getDepositAmountAttribute(): float
    {
        return round((float) $this->total * (float) $this->deposit_percentage / 100, 2);
    }

    public function getBalancePercentageAttribute(): float
    {
        return round(100 - (float) $this->deposit_percentage, 2);
    }

    public function getBalanceAmountAttribute(): float
    {
        return round((float) $this->total - $this->deposit_amount, 2);
    }
}
