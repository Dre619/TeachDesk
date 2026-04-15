<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ClassTransfer;
use App\Notifications\ClassTransferAcceptedNotification;
use App\Notifications\ClassTransferDeclinedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassTransferController extends Controller
{
    /**
     * Receiving teacher accepts the transfer via email link.
     */
    public function accept(string $token)
    {
        if (! Auth::check()) {
            session(['transfer_token_accept' => $token]);
            return redirect()->route('login')
                ->with('status', 'Please log in to accept the class transfer.');
        }

        $transfer = ClassTransfer::where('token', $token)
            ->where('status', 'pending')
            ->with(['classroom', 'fromUser', 'toUser'])
            ->first();

        if (! $transfer) {
            return redirect()->route('dashboard')
                ->with('error', 'This transfer link is invalid or has already been used.');
        }

        if ($transfer->to_user_id !== Auth::id()) {
            return redirect()->route('dashboard')
                ->with('error', 'This transfer request was sent to a different account.');
        }

        DB::transaction(function () use ($transfer) {
            // Hand over class ownership
            $transfer->classroom->update(['user_id' => $transfer->to_user_id]);

            // Mark transfer complete
            $transfer->update([
                'status'       => 'accepted',
                'responded_at' => now(),
                'token'        => null,
            ]);

            // Notify the original owner
            $transfer->fromUser->notify(
                new ClassTransferAcceptedNotification($transfer)
            );
        });

        return redirect()->route('dashboard')
            ->with('success', "You are now the form teacher for {$transfer->classroom->name}. It has been added to your classes.");
    }

    /**
     * Receiving teacher declines the transfer via email link.
     */
    public function decline(string $token)
    {
        if (! Auth::check()) {
            session(['transfer_token_decline' => $token]);
            return redirect()->route('login')
                ->with('status', 'Please log in to decline the class transfer.');
        }

        $transfer = ClassTransfer::where('token', $token)
            ->where('status', 'pending')
            ->with(['classroom', 'fromUser', 'toUser'])
            ->first();

        if (! $transfer) {
            return redirect()->route('dashboard')
                ->with('error', 'This transfer link is invalid or has already been used.');
        }

        if ($transfer->to_user_id !== Auth::id()) {
            return redirect()->route('dashboard')
                ->with('error', 'This transfer request was sent to a different account.');
        }

        $transfer->update([
            'status'       => 'declined',
            'responded_at' => now(),
            'token'        => null,
        ]);

        $transfer->fromUser->notify(
            new ClassTransferDeclinedNotification($transfer)
        );

        return redirect()->route('dashboard')
            ->with('info', "You have declined the transfer of {$transfer->classroom->name}.");
    }
}
