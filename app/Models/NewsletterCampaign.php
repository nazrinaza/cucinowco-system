<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['segments' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
