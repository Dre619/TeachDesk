<?php

namespace App\Actions;

use App\Models\ClassRoom;
use App\Models\ClassRoomMember;
use App\Models\User;
use App\Notifications\ClassInviteNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteMemberAction
{
    public function execute(
        ClassRoom $classroom,
        string    $email,
        string    $subject,
        User      $invitedBy,
    ): ClassRoomMember {

        // Find or create the invitee's account.
        // If they don't have one yet, a placeholder is created —
        // they set their password the first time they log in
        // (works with Laravel Fortify / Breeze password-reset flow).
        $invitee = User::firstOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name'     => ucwords(explode('@', $email)[0]),
                'password' => Hash::make(Str::random(32)),
            ],
        );

        // Create (or refresh) the membership record.
        // Using updateOrCreate so a re-invite after a decline works cleanly.
        $member = ClassRoomMember::updateOrCreate(
            [
                'class_id' => $classroom->id,
                'user_id'  => $invitee->id,
                'subject'  => $subject,
            ],
            [
                'invited_by'   => $invitedBy->id,
                'role'         => 'subject_teacher',
                'invite_token' => ClassRoomMember::generateToken(),
                'status'       => 'pending',
                'accepted_at'  => null,
            ],
        );

        // Send email + in-app notification
        $invitee->notify(new ClassInviteNotification($member->load(['classroom', 'invitedBy'])));

        return $member;
    }
}
