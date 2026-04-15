<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'emis_number',
        'name',
        'province',
        'district',
        'type',
        'created_by',
    ];

    // ── Constants ──────────────────────────────────────────────

    const PROVINCES = [
        'Central', 'Copperbelt', 'Eastern', 'Luapula',
        'Lusaka', 'Muchinga', 'Northern', 'North-Western',
        'Southern', 'Western',
    ];

    const TYPES = [
        'primary'   => 'Primary School',
        'secondary' => 'Secondary School',
        'high'      => 'High School',
        'combined'  => 'Combined School',
        'special'   => 'Special School',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function teachers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeByEmis($query, string $emis)
    {
        return $query->where('emis_number', strtoupper(trim($emis)));
    }

    // ── Helpers ────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getTeacherCountAttribute(): int
    {
        return $this->teachers()->count();
    }
}
