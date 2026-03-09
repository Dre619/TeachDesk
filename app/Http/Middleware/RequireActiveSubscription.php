<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    /**
     * Route names the middleware will never block.
     * Add billing, logout, and any grace pages here.
     * Wildcard suffix ".*" is supported (e.g. "billing.*").
     */
    protected array $except = [
        'subscription.expired',
        'subscription.plans',
        'billing.*',
        'logout',
        'filament.*',   // keep admin panel always accessible
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Unauthenticated users are not our concern — let the auth
        //    middleware handle them before this one runs.
        if (! $request->user()) {
            return $next($request);
        }

        if(auth()->check() && auth()->user()->role == 'super_admin')
            {
                return $next($request);
            }

        // 2. Never block explicitly excluded routes.
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        $subscription = $this->getActiveSubscription($request);

        // 3. No subscription at all → hard redirect to plans page.
        if (! $subscription) {
            return $this->handleNoSubscription($request);
        }

        // 4. Subscription exists but is expired → redirect to expired page.
        if (! $subscription->isActive()) {
            return $this->handleExpiredSubscription($request, $subscription);
        }

        // 5. Subscription is valid — attach it to the request for downstream use.
        $request->merge(['active_subscription' => $subscription]);

        // 6. Warn the user if they're within the expiry window (≤7 days).
        //    We flash a session key so any layout can display a renewal banner.
        if ($subscription->isExpiringSoon()) {
            session()->flash(
                'subscription_warning',
                "Your subscription expires in {$subscription->daysRemaining()} day(s). Please renew to avoid interruption."
            );
        }

        return $next($request);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Eager-load the user's latest active subscription (and its plan).
     */
    private function getActiveSubscription(Request $request): ?Subscription
    {
        return Subscription::with('plan')
            ->where('user_id', $request->user()->id)
            ->active()              // uses scopeActive() on the model
            ->latest('ends_at')    // prefer the subscription that expires last
            ->first();
    }

    /**
     * User has no subscription at all.
     *
     * API  → 403 JSON
     * Web  → redirect to pricing/plans page
     */
    private function handleNoSubscription(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'A subscription is required to access this resource.',
                'code'    => 'NO_SUBSCRIPTION',
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('subscription.plans')
            ->with('error', 'You need an active subscription to access this page. Please choose a plan to continue.');
    }

    /**
     * User has a subscription but it has expired.
     *
     * API  → 403 JSON with expiry details
     * Web  → redirect to a dedicated "subscription expired" page
     */
    private function handleExpiredSubscription(Request $request, Subscription $subscription): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'    => 'Your subscription has expired.',
                'code'       => 'SUBSCRIPTION_EXPIRED',
                'expired_at' => $subscription->ends_at->toIso8601String(),
                'plan'       => $subscription->plan?->name,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('subscription.expired')
            ->with('expired_subscription', $subscription);
    }

    /**
     * Check if the current request targets an excluded route.
     * Supports exact names and wildcard prefix patterns (e.g. "billing.*").
     */
    private function isExcluded(Request $request): bool
    {
        $currentRoute = $request->route()?->getName();

        if (! $currentRoute) {
            return false;
        }

        foreach ($this->except as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = rtrim($pattern, '.*');
                if (str_starts_with($currentRoute, $prefix)) {
                    return true;
                }
            } elseif ($currentRoute === $pattern) {
                return true;
            }
        }

        return false;
    }
}
