<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named ClassRoom (not Class) because `class` is a reserved keyword in PHP.
 * The underlying table is still `classes`.
 */
class ClassRoom extends Model
{
    use HasFactory;

    /**
     * Map model to the `classes` database table.
     */
    protected $table = 'classes';

    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'academic_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All students in this class, including soft-deleted ones.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * Only active (non-deleted) students.
     */
    public function activeStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id')->whereNull('deleted_at');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class, 'class_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'class_id');
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    /**
     * Only classes belonging to the given teacher.
     */
    public function scopeForTeacher($query, int $userId)
    {
        return $query->where('user_id', $userId)
        ->orWhereHas('members',function($q) use ($userId){
            $q->where('user_id',$userId)
            ->where('status','accepted');
        });
    }

    /**
     * Only currently active (non-archived) classes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter by academic year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('academic_year', $year);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Get the count of active students in this class.
     */
    public function studentCount(): int
    {
        return $this->activeStudents()->count();
    }

    /**
     * Display label: "Grade 8A · 2025" or "Grade 8A"
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' · ' . $this->academic_year;
    }

    public function members(): HasMany
    {
        return $this->hasMany(\App\Models\ClassRoomMember::class, 'class_id');
    }

    public function acceptedMembers(): HasMany
    {
        return $this->hasMany(\App\Models\ClassRoomMember::class, 'class_id')
                    ->where('status', 'accepted');
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * True if the given user owns this class (is the form teacher).
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Returns all subjects this user teaches in this class (empty array if they are the owner).
     * Supports teachers with multiple subjects and co-teachers.
     *
     * @return string[]
     */
    public function memberSubjects(int $userId): array
    {
        return $this->acceptedMembers()
            ->where('user_id', $userId)
            ->pluck('subject')
            ->toArray();
    }

    /**
     * All subjects being taught in this class
     * (owner's subject + all accepted subject teachers' subjects).
     */
    public function allSubjects(): array
    {
        $memberSubjects = $this->acceptedMembers()->pluck('subject')->toArray();
        return array_values(array_unique(array_merge([$this->subject], $memberSubjects)));
    }
}
