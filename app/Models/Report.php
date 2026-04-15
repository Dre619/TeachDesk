<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'term',
        'academic_year',
        'pdf_path',
        'status',
        'generated_at',
        'teacher_comment',
        'conduct_grade',
        'form_teacher_comment',
        'head_teacher_comment',
        'share_token',
        'parent_email',
    ];

    protected function casts(): array
    {
        return [
            'term'          => 'integer',
            'academic_year' => 'integer',
            'generated_at'  => 'datetime',
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

    public function scopeForTeacher($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTerm($query, int $term, ?int $year = null)
    {
        $query->where('term', $term);

        if ($year) {
            $query->where('academic_year', $year);
        }

        return $query;
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public function isGenerated(): bool
    {
        return $this->status === 'generated' && $this->pdf_path !== null;
    }

    /**
     * Mark the report as generated and record the file path.
     */
    public function markAsGenerated(string $pdfPath): bool
    {
        return $this->update([
            'status'       => 'generated',
            'pdf_path'     => $pdfPath,
            'generated_at' => now(),
        ]);
    }

    /**
     * Get the full storage URL for downloading the PDF.
     * Returns null if not yet generated.
     */
    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return Storage::url($this->pdf_path);
    }

    /**
     * Delete the stored PDF file from disk.
     */
    public function deletePdf(): void
    {
        if ($this->pdf_path && Storage::exists($this->pdf_path)) {
            Storage::delete($this->pdf_path);
        }
    }

    /**
     * Build the standard storage path for a report PDF.
     * e.g. "reports/2025/term-1/student-42-term-1-2025.pdf"
     */
    public static function buildPdfPath(int $studentId, int $term, int $year): string
    {
        return "reports/{$year}/term-{$term}/student-{$studentId}-term-{$term}-{$year}.pdf";
    }

    /**
     * Get or create a report record for the given student/term/year.
     * Uses firstOrCreate to honour the unique constraint.
     */
    public static function findOrInitialise(
        int $studentId,
        int $userId,
        int $term,
        int $year
    ): static {
        return static::firstOrCreate(
            [
                'student_id'    => $studentId,
                'term'          => $term,
                'academic_year' => $year,
            ],
            [
                'user_id' => $userId,
                'status'  => 'draft',
            ]
        );
    }

    /**
     * Human-readable label: "Term 2 · 2025"
     */
    public function getLabelAttribute(): string
    {
        return "Term {$this->term} · {$this->academic_year}";
    }

    /**
     * Generate (or return existing) a share token and persist it.
     */
    public function generateShareToken(): string
    {
        if (! $this->share_token) {
            $this->update(['share_token' => Str::random(48)]);
        }

        return $this->share_token;
    }

    /**
     * The public URL parents can open to view the report card.
     */
    public function getShareUrlAttribute(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return route('report.shared', $this->share_token);
    }
}
