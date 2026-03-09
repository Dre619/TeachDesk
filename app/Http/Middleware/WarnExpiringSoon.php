<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WarnExpiringSoon
{
    /**
     * Injects a session flash warning when the user's subscription
     * is within the expiry window (≤ 7 days by default).
     *
     * This middleware should be applied AFTER RequireActiveSubscription
     * so that $request->activeSubscription is already populated.
     *
     * Usage:
     *   ->middleware(['subscription', 'subscription.warn'])
     */
    public function handle(Request $request, Closure $next, int $warningDays = 7): Response
    {
        if (Auth::check()) {
            // Prefer the subscription already resolved by RequireActiveSubscription;
            // fall back to a fresh query if used standalone.
            $subscription = $request->get('activeSubscription')
                ?? Auth::user()
                    ->subscriptions()
                    ->whereIn('status', ['active', 'trial'])
                    ->where('ends_at', '>', now())
                    ->latest('starts_at')
                    ->first();

            if ($subscription && $subscription->isExpiringSoon()) {
                $days = $subscription->daysRemaining();

                session()->flash('subscription_expiry_warning', [
                    'days'    => $days,
                    'ends_at' => $subscription->ends_at->toDateString(),
                    'message' => $days === 0
                        ? 'Your subscription expires today!'
                        : "Your subscription expires in {$days} day" . ($days === 1 ? '' : 's') . '.',
                    'renew_url' => route('subscription.plans'),
                ]);
            }
        }

        return $next($request);
    }
}
