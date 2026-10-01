<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TontineMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'tontine_id',
        'member_phone',
        'turn_order',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'tontine_id' => 'integer',
        'turn_order' => 'integer',
        'joined_at' => 'datetime',
    ];

    /**
     * Relation to Tontine.
     */
    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class, 'tontine_id');
    }
}
