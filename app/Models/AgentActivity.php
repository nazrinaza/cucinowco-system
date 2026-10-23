<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentActivity extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
