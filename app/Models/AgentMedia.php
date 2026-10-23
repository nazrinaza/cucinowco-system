<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AgentMedia extends Model
{
    protected $table = 'agent_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_published', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
