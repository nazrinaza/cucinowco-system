<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'is_active' => 'boolean', 'metadata' => 'array'];
    }
}
