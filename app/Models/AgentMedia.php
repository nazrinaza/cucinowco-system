<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMedia extends Model
{
    protected $table = 'agent_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AgentMediaFolder::class, 'folder_id');
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function isVideo(): bool
    {
        return $this->mime_type === 'video/mp4';
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_published', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
