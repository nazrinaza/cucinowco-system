<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $guarded = [];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
