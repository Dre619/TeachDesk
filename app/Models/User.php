<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'school_name',
        'city',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function behaviourLogs(): HasMany
    {
        return $this->hasMany(BehaviourLog::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ──────────────────────────────────────────
    // Subscription helpers
    // ──────────────────────────────────────────

    /**
     * Get the teacher's current active or trial subscription.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->where('ends_at', '>', now())
            ->latestOfMany();
    }

    /**
     * Check whether the teacher's subscription is currently valid.
     */
    public function isSubscribed(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Check whether the teacher can access a specific feature.
     *
     * Usage: $user->can('report_cards')
     * Feature keys match the JSON keys in subscription_plans.features.
     */
    public function hasFeature(string $feature): bool
    {
        $subscription = $this->activeSubscription()->with('plan')->first();

        if (! $subscription) {
            return false;
        }

        return (bool) data_get($subscription->plan->features, $feature, false);
    }

    /**
     * Get the maximum number of classes allowed by the current plan.
     * Returns null for unlimited.
     */
    public function maxClasses(): ?int
    {
        $subscription = $this->activeSubscription()->with('plan')->first();

        if (! $subscription) {
            return 0;
        }

        return data_get($subscription->plan->features, 'max_classes');
    }

    /**
     * Get the teacher's current plan slug (e.g. 'basic', 'pro').
     * Returns null if not subscribed.
     */
    public function planSlug(): ?string
    {
        $subscription = $this->activeSubscription()->with('plan')->first();

        return $subscription?->plan->slug;
    }

    // ──────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────

    /**
     * Get the teacher's first name from their full name.
     */
    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name)[0];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
