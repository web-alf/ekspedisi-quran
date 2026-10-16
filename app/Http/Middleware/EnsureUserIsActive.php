<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            
            // Check if user is still active
            if (!$user->is_active) {
                // Log the event
                \Log::warning('Inactive user attempted to access system', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'route' => $request->route()->getName()
                ]);
                
                // Logout the user
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                // Redirect to login with message
                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Anda telah dinonaktifkan. Sesi login Anda telah diakhiri.'
                ]);
            }
        }

        return $next($request);
    }
}
