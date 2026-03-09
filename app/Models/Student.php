<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'class_id',
        'user_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
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

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    /**
     * Students belonging to a specific teacher.
     */
    public function scopeForTeacher($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Students in a specific class.
     */
    public function scopeInClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Order alphabetically by last name, then first name.
     */
    public function scopeAlphabetical($query)
    {
        return $query->orderBy('last_name')->orderBy('first_name');
    }

    // ──────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────

    /**
     * Full name: "Mwila Banda"
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Reversed name for registers: "Banda, Mwila"
     */
    public function getRegisterNameAttribute(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    /**
     * Age calculated from date_of_birth.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Get this student's attendance record for a specific date.
     */
    public function attendanceOn(string $date): ?Attendance
    {
        return $this->attendance()->whereDate('date', $date)->first();
    }

    /**
     * Get the attendance rate for this student as a percentage.
     * Counts present + late as attended.
     */
    public function attendanceRate(string $startDate, string $endDate): float
    {
        $total = $this->attendance()
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $attended = $this->attendance()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($attended / $total) * 100, 1);
    }
}
