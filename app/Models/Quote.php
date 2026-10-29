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
            'contact_people' => 'array',
            'viewed_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2', 'total' => 'decimal:2',
            'document_date' => 'date', 'deposit_percentage' => 'decimal:2',
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

    public function proformaInvoice(): HasOne
    {
        return $this->hasOne(ProformaInvoice::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisitRequest::class, 'site_visit_request_id');
    }

    public function getReferralLabelAttribute(): string
    {
        return $this->siteVisit?->referral_label ?? 'Direct';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_user_id');
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: 'RE: QUOTATION FOR CLEANING SERVICE AT '.strtoupper($this->customer?->company_name ?: $this->customer?->name ?: 'CUSTOMER SITE');
    }

    public function getDepositAmountAttribute(): float
    {
        return round((float) $this->total * (float) ($this->deposit_percentage ?? 50) / 100, 2);
    }

    public function getBalancePercentageAttribute(): float
    {
        return round(100 - (float) ($this->deposit_percentage ?? 50), 2);
    }

    public function getBalanceAmountAttribute(): float
    {
        return round((float) $this->total - $this->deposit_amount, 2);
    }
}
