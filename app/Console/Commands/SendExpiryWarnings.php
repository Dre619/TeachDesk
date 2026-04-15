<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiring;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendExpiryWarnings extends Command
{
    protected $signature = 'subscriptions:send-expiry-warnings';

    protected $description = 'Send expiry warning emails to users whose subscriptions expire in 7 or 1 days.';

    public function handle(): int
    {
        $warningDays = [7, 1];
        $sent = 0;

        foreach ($warningDays as $days) {
            $date = now()->addDays($days)->toDateString();

            $subscriptions = Subscription::query()
                ->with(['user', 'plan'])
                ->whereIn('status', ['active', 'trial'])
                ->whereDate('ends_at', $date)
                ->get();

            foreach ($subscriptions as $subscription) {
                $user = $subscription->user;

                if (! $user?->email) {
                    continue;
                }

                Mail::to($user->email)->queue(
                    new SubscriptionExpiring($user, $subscription, $days)
                );

                $sent++;
                $this->line("  Queued warning for {$user->email} ({$days}d remaining)");
            }
        }

        $this->info("Done. {$sent} warning(s) queued.");

        return self::SUCCESS;
    }
}
