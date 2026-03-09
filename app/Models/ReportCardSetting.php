<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardSetting extends Model
{
    protected $fillable = [
        'user_id',
        'class_id',
        'school_name',
        'school_motto',
        'school_logo',
        'accent_color',
        'show_attendance',
        'show_conduct',
        'show_grading_scale',
        'show_signatures',
        'footer_note',
    ];

    protected function casts(): array
    {
        return [
            'show_attendance'    => 'boolean',
            'show_conduct'       => 'boolean',
            'show_grading_scale' => 'boolean',
            'show_signatures'    => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get settings for a teacher + class, falling back to their global default,
     * then to hardcoded defaults if nothing exists yet.
     */
    public static function forTeacherAndClass(int $userId, int $classId): static
    {
        // Class-specific settings take precedence
        return static::firstOrNew(
            ['user_id' => $userId, 'class_id' => $classId],
            static::defaults($userId),
        );
    }

    /**
     * The default attribute values for a new settings record.
     */
    public static function defaults(int $userId): array
    {
        return [
            'user_id'            => $userId,
            'class_id'           => null,
            'school_name'        => 'Student Report Card',
            'school_motto'       => null,
            'school_logo'        => null,
            'accent_color'       => '#4f46e5',
            'show_attendance'    => true,
            'show_conduct'       => true,
            'show_grading_scale' => true,
            'show_signatures'    => true,
            'footer_note'        => null,
        ];
    }
}
