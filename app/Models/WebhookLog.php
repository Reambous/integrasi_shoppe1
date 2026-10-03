<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_type',
        'shop_id',
        'payload',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        // Disimpan sebagai LONGTEXT raw JSON; cast array memudahkan baca.
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
