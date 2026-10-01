<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'tontine_round_id',
        'member_phone',
        'amount',
        'payment_provider',
        'transaction_reference',
        'payment_timestamp',
        'punctuality_status',
        'payment_status',
    ];

    protected $casts = [
        'tontine_round_id' => 'integer',
        'amount' => 'decimal:2',
        'payment_timestamp' => 'datetime',
    ];

    /**
     * Relation to TontineRound.
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(TontineRound::class, 'tontine_round_id');
    }
}
