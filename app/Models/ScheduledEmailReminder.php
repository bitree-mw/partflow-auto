<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledEmailReminder extends Model
{
    protected $fillable = [
        'type', 'period_start', 'status', 'item_count', 'sent_at', 'failure_type',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'item_count' => 'integer',
        ];
    }
}
