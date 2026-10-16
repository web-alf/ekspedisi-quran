<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckSessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $lastActivity = Session::get('last_activity');
            $timeout = config('session.timeout') * 60; // Convert to seconds

            if ($lastActivity && (time() - $lastActivity > $timeout)) {
                Auth::logout();
                Session::flush();
                return redirect('/')->with('message', 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.');
            }

            Session::put('last_activity', time());
        }

        return $next($request);
    }
} 