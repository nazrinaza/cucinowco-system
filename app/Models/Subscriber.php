<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['segments' => 'array', 'subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
