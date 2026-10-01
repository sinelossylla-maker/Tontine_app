<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TontineRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'tontine_id',
        'round_number',
        'beneficiary_phone',
        'due_date',
        'total_expected_amount',
        'total_collected_amount',
        'status',
        'payout_status',
        'payout_at',
    ];

    protected $casts = [
        'tontine_id' => 'integer',
        'round_number' => 'integer',
        'due_date' => 'datetime',
        'payout_at' => 'datetime',
        'total_expected_amount' => 'decimal:2',
        'total_collected_amount' => 'decimal:2',
    ];

    /**
     * Relation to Tontine.
     */
    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class, 'tontine_id');
    }

    /**
     * Relation to Contributions.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'tontine_round_id');
    }

    /**
     * Check if total collected amount reaches or exceeds expected amount.
     */
    public function isFullyCollected(): bool
    {
        return (float) $this->total_collected_amount >= (float) $this->total_expected_amount;
    }
}
