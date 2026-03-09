<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ClassRoomMember;
use App\Notifications\InviteAcceptedNotification;
use App\Notifications\InviteDeclinedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    /**
     * Accept via email link.
     * If not logged in, redirects to login then back here.
     */
    public function accept(string $token)
    {
        // Force login before accepting
        if (! Auth::check()) {
            session(['invite_token' => $token]);
            return redirect()->route('login')
                ->with('status', 'Please log in to accept your class invitation.');
        }

        $member = ClassRoomMember::where('invite_token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $member) {
            return redirect()->route('user.class.rooms')
                ->with('error', 'This invitation link is invalid or has already been used.');
        }

        // Make sure the logged-in user is the intended recipient
        if ($member->user_id !== Auth::id()) {
            return redirect()->route('user.class.rooms')
                ->with('error', 'This invitation was sent to a different account.');
        }

        $member->update([
            'status'       => 'accepted',
            'accepted_at'  => now(),
            'invite_token' => null,
        ]);

        // Notify the form teacher
        $member->invitedBy->notify(
            new InviteAcceptedNotification($member->load(['classroom', 'teacher']))
        );

        return redirect()->route('user.class.rooms')
            ->with('success', "You've joined {$member->classroom->name} as {$member->subject} teacher. You can now enter marks for your subject.");
    }

    /**
     * Decline via email link.
     * If logged in, the user must be the intended recipient.
     */
    public function decline(string $token)
    {
        if (! Auth::check()) {
            session(['invite_token_decline' => $token]);
            return redirect()->route('login')
                ->with('status', 'Please log in to decline your class invitation.');
        }

        $member = ClassRoomMember::where('invite_token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $member) {
            return redirect()->route('user.class.rooms')
                ->with('error', 'This invitation link is invalid or has already been used.');
        }

        if ($member->user_id !== Auth::id()) {
            return redirect()->route('user.class.rooms')
                ->with('error', 'This invitation was sent to a different account.');
        }

        $member->update([
            'status'       => 'declined',
            'invite_token' => null,
        ]);

        // Notify form teacher
        $member->invitedBy->notify(
            new InviteDeclinedNotification($member->load(['classroom', 'teacher']))
        );

        return redirect()->route('user.class.rooms')
            ->with('info', 'You have declined the class invitation.');
    }
}
