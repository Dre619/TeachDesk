<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviourLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'type',
        'category',
        'description',
        'action_taken',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForTeacher($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePositive($query)
    {
        return $query->where('type', 'positive');
    }

    public function scopeNegative($query)
    {
        return $query->where('type', 'negative');
    }

    /**
     * Filter logs within a date range.
     */
    public function scopeInDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Most recent logs first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public function isPositive(): bool
    {
        return $this->type === 'positive';
    }

    public function isNegative(): bool
    {
        return $this->type === 'negative';
    }

    /**
     * Icon/emoji for use in UI based on type.
     */
    public function getTypeIconAttribute(): string
    {
        return $this->type === 'positive' ? '✅' : '⚠️';
    }
}
