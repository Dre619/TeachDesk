<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Impersonate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating_id')) {
            $user = User::find(session('impersonating_id'));

            if ($user) {
                Auth::setUser($user);
            } else {
                // Target user no longer exists — clean up
                session()->forget(['impersonating_id', 'impersonating_original_id']);
            }
        }

        return $next($request);
    }
}
