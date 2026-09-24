<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteVisitPhoto extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'site_visit_request_id' => 'integer',
            'uploaded_by_user_id' => 'integer',
            'before_photo_id' => 'integer',
        ];
    }

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisitRequest::class, 'site_visit_request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function beforeReference(): BelongsTo
    {
        return $this->belongsTo(self::class, 'before_photo_id');
    }

    public function afterPhotos(): HasMany
    {
        return $this->hasMany(self::class, 'before_photo_id');
    }
}
