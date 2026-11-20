<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRole
{
    /** Usage: ->middleware('role:super_admin') */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if ($user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }
        if ($roles && ! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
