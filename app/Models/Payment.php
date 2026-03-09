<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_id',
        'amount_zmw',
        'method',
        'gateway_ref',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_zmw' => 'decimal:2',
            'paid_at'    => 'datetime',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Mark this payment as successful and record the timestamp.
     */
    public function markAsSuccessful(?string $gatewayRef = null): bool
    {
        return $this->update([
            'status'      => 'success',
            'paid_at'     => now(),
            'gateway_ref' => $gatewayRef ?? $this->gateway_ref,
        ]);
    }

    /**
     * Get the formatted amount string. e.g. "K34.00"
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'K' . number_format($this->amount_zmw, 2);
    }

    /**
     * Human-readable method label.
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'mtn'    => 'MTN Mobile Money',
            'airtel' => 'Airtel Money',
            'card'   => 'Card',
            'paypal' => 'PayPal',
            'manual' => 'Manual (Admin)',
            default  => ucfirst($this->method),
        };
    }
}
