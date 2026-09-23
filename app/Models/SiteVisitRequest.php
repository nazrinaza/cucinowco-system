<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisitRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'clean_types' => 'array',
            'preferred_date' => 'date',
            'contacted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCleanTypesLabelAttribute(): string
    {
        return $this->clean_types
            ? collect($this->clean_types)->map(fn ($type) => config('site_visits.clean_types.'.$type, $type))->implode(', ')
            : ($this->service?->name ?? 'To be confirmed');
    }

    public function getSpaceLabelAttribute(): string
    {
        return config('site_visits.spaces.'.$this->space_type, (string) str($this->space_type)->replace('_', ' ')->title());
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
