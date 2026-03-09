<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    /**
     * Subscriptions that are currently valid (active or trial, not yet expired).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trial'])
                     ->where('ends_at', '>', now());
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                     ->orWhere(fn ($q) => $q->where('ends_at', '<=', now()));
    }

    // ──────────────────────────────────────────
    // Accessors & helpers
    // ──────────────────────────────────────────

    /**
     * Check if this subscription is currently valid.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial'])
            && $this->ends_at->isFuture();
    }

    /**
     * Number of days remaining on this subscription.
     */
    public function daysRemaining(): int
    {
        if ($this->ends_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($this->ends_at);
    }

    /**
     * Whether the subscription is in the warning window (≤7 days left).
     */
    public function isExpiringSoon(): bool
    {
        return $this->isActive() && $this->daysRemaining() <= 7;
    }
}
