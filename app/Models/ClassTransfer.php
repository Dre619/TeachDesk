<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClassTransfer extends Model
{
    protected $fillable = [
        'class_id',
        'from_user_id',
        'to_user_id',
        'status',
        'message',
        'token',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    // ── Helpers ────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isAccepted(): bool  { return $this->status === 'accepted'; }
    public function isDeclined(): bool  { return $this->status === 'declined'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }
}
