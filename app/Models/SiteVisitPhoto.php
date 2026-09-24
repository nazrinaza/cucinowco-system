<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisitPhoto extends Model
{
    protected $guarded = [];

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisitRequest::class, 'site_visit_request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
