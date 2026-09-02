<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function siteVisitRequests(): HasMany
    {
        return $this->hasMany(SiteVisitRequest::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
