<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price_zmw',
        'billing_cycle',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_zmw'  => 'decimal:2',
            'features'   => 'array',      // Auto JSON encode/decode
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'plan_id');
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    /**
     * Only return plans visible to the public (not archived).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Check whether this plan includes a specific feature.
     * Usage: $plan->hasFeature('report_cards')
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->features, $feature, false);
    }

    /**
     * Get the formatted price string.
     * e.g. "K12.50"
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'K' . number_format($this->price_zmw, 2);
    }
}
