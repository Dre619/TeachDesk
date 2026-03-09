<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use HasFactory;

    /**
     * Zambian ECZ grading scale.
     * Used when calculating and storing the `grade` field.
     */
    const GRADING_SCALE = [
        90 => 'A',
        80 => 'B',
        70 => 'C',
        60 => 'D',
        50 => 'E',
        0  => 'F',
    ];

    protected $fillable = [
        'student_id',
        'class_id',
        'user_id',
        'subject',
        'term',
        'academic_year',
        'type',
        'score',
        'max_score',
        'grade',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'score'         => 'decimal:2',
            'max_score'     => 'decimal:2',
            'term'          => 'integer',
            'academic_year' => 'integer',
        ];
    }

    // ──────────────────────────────────────────
    // Lifecycle hooks
    // ──────────────────────────────────────────

    /**
     * Auto-calculate and store the grade whenever a score is saved.
     */
    protected static function booted(): void
    {
        static::saving(function (Assessment $assessment) {
            $assessment->grade = $assessment->calculateGrade();
        });
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForTerm($query, int $term, ?int $year = null)
    {
        $query->where('term', $term);

        if ($year) {
            $query->where('academic_year', $year);
        }

        return $query;
    }

    public function scopeForSubject($query, string $subject)
    {
        return $query->where('subject', $subject);
    }

    // ──────────────────────────────────────────
    // Helpers & accessors
    // ──────────────────────────────────────────

    /**
     * Calculate the percentage score.
     */
    public function getPercentageAttribute(): float
    {
        if ($this->max_score == 0) {
            return 0.0;
        }

        return round(($this->score / $this->max_score) * 100, 1);
    }

    /**
     * Calculate the grade from the current score using the Zambian ECZ scale.
     */
    public function calculateGrade(): string
    {
        $percentage = $this->percentage;

        foreach (self::GRADING_SCALE as $threshold => $grade) {
            if ($percentage >= $threshold) {
                return $grade;
            }
        }

        return 'F';
    }

    /**
     * Human-readable assessment type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'test'       => 'Class Test',
            'exam'       => 'Examination',
            'assignment' => 'Assignment',
            'ca'         => 'Continuous Assessment',
            'other'      => 'Other',
            default      => ucfirst($this->type),
        };
    }

    /**
     * Get all subject assessments for a student in a term, averaged per subject.
     * Useful for building the report card data.
     *
     * Returns: ['Mathematics' => ['average' => 72.5, 'grade' => 'C'], ...]
     */
    public static function summaryForStudent(int $studentId, int $term, int $year): array
    {
        return static::forStudent($studentId)
            ->forTerm($term, $year)
            ->get()
            ->groupBy('subject')
            ->map(function ($subjectAssessments) {
                $average = $subjectAssessments->avg('percentage');
                $grade   = (new static(['score' => $average, 'max_score' => 100]))->calculateGrade();

                return [
                    'average' => round($average, 1),
                    'grade'   => $grade,
                    'count'   => $subjectAssessments->count(),
                ];
            })
            ->toArray();
    }
}
