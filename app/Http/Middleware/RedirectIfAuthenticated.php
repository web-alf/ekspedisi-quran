<?php

namespace App\Http\Middleware;

use App\Support\BerandaPeran;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Pengguna yang sudah login tapi membuka halaman guest (mis. /login lewat
     * bookmark) diterahkan ke BERANDA perannya — bukan selalu /admin/dashboard,
     * supaya peran gudang tidak mendarat di halaman yang tidak ada di menunya.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect(BerandaPeran::dariAuth(Auth::guard($guard)->user()));
            }
        }

        return $next($request);
    }
}
