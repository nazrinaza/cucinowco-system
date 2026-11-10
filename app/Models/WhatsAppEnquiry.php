<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppEnquiry extends Model
{
    protected $table = 'whatsapp_enquiries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'whatsapp_consent' => 'boolean',
            'submitted_to_meta_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
