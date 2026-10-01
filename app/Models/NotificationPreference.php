<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_phone',
        'sms_enabled',
        'push_enabled',
        'payment_reminders',
        'payout_alerts',
    ];

    protected $casts = [
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'payment_reminders' => 'boolean',
        'payout_alerts' => 'boolean',
    ];
}
