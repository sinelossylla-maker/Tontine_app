<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tontine extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_phone',
        'name',
        'contribution_amount',
        'frequency',
        'start_date',
        'max_members',
        'invitation_code',
        'status',
    ];

    protected $casts = [
        'contribution_amount' => 'decimal:2',
        'max_members' => 'integer',
        'start_date' => 'date',
    ];

    /**
     * Boot function to automatically generate a unique invitation code on creation.
     */
    protected static function booted(): void
    {
        static::creating(function ($tontine) {
            if (empty($tontine->invitation_code)) {
                $tontine->invitation_code = static::generateUniqueInvitationCode();
            }
        });
    }

    /**
     * Generate a unique 8-character alphanumeric invitation code.
     */
    public static function generateUniqueInvitationCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::where('invitation_code', $code)->exists());

        return $code;
    }

    /**
     * Relation to TontineMember.
     */
    public function members(): HasMany
    {
        return $this->hasMany(TontineMember::class, 'tontine_id');
    }

    /**
     * Relation to TontineRound.
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(TontineRound::class, 'tontine_id');
    }

    /**
     * Check if the tontine group has reached its maximum members capacity.
     */
    public function isFull(): bool
    {
        return $this->members()->count() >= $this->max_members;
    }
}
