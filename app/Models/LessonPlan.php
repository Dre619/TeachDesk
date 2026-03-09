<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'class_id',
        'title',
        'subject',
        'topic',
        'term',
        'week_number',
        'academic_year',
        'duration_minutes',
        'objectives',
        'resources',
        'content',
        'assessment',
        'homework',
    ];

    protected function casts(): array
    {
        return [
            'term'             => 'integer',
            'week_number'      => 'integer',
            'academic_year'    => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
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

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForSubject($query, string $subject)
    {
        return $query->where('subject', $subject);
    }

    /**
     * Order by week number for term view grid.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('week_number')->orderBy('created_at');
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Duplicate this lesson plan as a new record (template reuse).
     * Returns the new unsaved model — caller decides when to save.
     */
    public function duplicate(): static
    {
        return new static([
            'user_id'          => $this->user_id,
            'class_id'         => $this->class_id,
            'title'            => $this->title . ' (Copy)',
            'subject'          => $this->subject,
            'topic'            => $this->topic,
            'term'             => $this->term,
            'week_number'      => $this->week_number,
            'academic_year'    => $this->academic_year,
            'duration_minutes' => $this->duration_minutes,
            'objectives'       => $this->objectives,
            'resources'        => $this->resources,
            'content'          => $this->content,
            'assessment'       => $this->assessment,
            'homework'         => $this->homework,
        ]);
    }

    /**
     * Human-readable duration, e.g. "40 min" or "1 hr 15 min".
     */
    public function getDurationLabelAttribute(): ?string
    {
        if (! $this->duration_minutes) return null;
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;
        return $h > 0
            ? ($m > 0 ? "{$h} hr {$m} min" : "{$h} hr")
            : "{$m} min";
    }

    /**
     * Get the term name as a label.
     */
    public function getTermLabelAttribute(): string
    {
        return match ($this->term) {
            1 => 'Term 1 (Jan–Apr)',
            2 => 'Term 2 (May–Aug)',
            3 => 'Term 3 (Sep–Dec)',
            default => "Term {$this->term}",
        };
    }

    /**
     * Strip HTML tags from content for plain text previews.
     */
    public function getContentPreviewAttribute(): string
    {
        return str($this->content)->stripTags()->limit(120)->toString();
    }
}
