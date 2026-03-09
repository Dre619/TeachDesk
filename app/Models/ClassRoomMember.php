<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassRoomMember extends Model
{
    protected $fillable = [
        'class_id',
        'user_id',
        'invited_by',
        'subject',
        'role',
        'invite_token',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function classroom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function isDeclined(): bool { return $this->status === 'declined'; }

    public static function generateToken(): string
    {
        return Str::random(40);
    }
}
