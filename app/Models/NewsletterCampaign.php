<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivery_started_at' => 'datetime',
            'next_batch_at' => 'datetime',
            'delivery_cursor' => 'integer',
            'delivery_max_subscriber_id' => 'integer',
            'delivery_batch_number' => 'integer',
            'processed_count' => 'integer',
        ];
    }
}
