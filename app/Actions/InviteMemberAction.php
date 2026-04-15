<?php

namespace App\Actions;

use App\Models\ClassRoom;
use App\Models\ClassRoomMember;
use App\Models\User;
use App\Notifications\ClassInviteNotification;

class InviteMemberAction
{
    public function execute(
        ClassRoom $classroom,
        string    $email,
        string    $subject,
        User      $invitedBy,
    ): ClassRoomMember {

        // Only existing accounts can be invited.
        $invitee = User::where('email', strtolower(trim($email)))->first();

        if (! $invitee) {
            throw new \InvalidArgumentException(
                "No account found for {$email}. The teacher must already have a TeachDesk account before they can be invited."
            );
        }

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
