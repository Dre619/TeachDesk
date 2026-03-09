<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'student_id',
        'class_id',
        'date',
        'status',
        'notes',
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

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    /**
     * Filter by a specific class.
     */
    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Filter by a specific date.
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Filter by a date range (for monthly summary).
     */
    public function scopeInMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Whether the student was in school (present or late counts as attended).
     */
    public function wasPresent(): bool
    {
        return in_array($this->status, ['present', 'late']);
    }

    /**
     * Bulk upsert attendance for a whole class on one date.
     *
     * Usage:
     *   Attendance::bulkUpsert($classId, '2025-03-10', [
     *       ['student_id' => 1, 'status' => 'present'],
     *       ['student_id' => 2, 'status' => 'absent', 'notes' => 'Sick'],
     *   ]);
     */
    public static function bulkUpsert(int $classId, string $date, array $records): void
    {
        $rows = array_map(function ($record) use ($classId, $date) {
            return [
                'student_id' => $record['student_id'],
                'class_id'   => $classId,
                'date'       => $date,
                'status'     => $record['status'],
                'notes'      => $record['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $records);

        // Upsert on the unique(student_id, date) constraint
        static::upsert($rows, ['student_id', 'date'], ['status', 'notes', 'updated_at']);
    }

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'present' => 'Present',
            'absent'  => 'Absent',
            'late'    => 'Late',
            default   => ucfirst($this->status),
        };
    }

    /**
     * Short code for use in register grids: P / A / L
     */
    public function getStatusCodeAttribute(): string
    {
        return match ($this->status) {
            'present' => 'P',
            'absent'  => 'A',
            'late'    => 'L',
            default   => '?',
        };
    }
}
