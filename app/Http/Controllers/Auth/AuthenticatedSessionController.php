<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // First, check if user exists and get user data
        $user = User::where('email', $request->email)->first();
        
        // If user doesn't exist or password is wrong
        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ]);
        }
        
        // Check if user account is active
        if (!$user->is_active) {
            // Log failed login attempt due to inactive account
            \Log::warning('Login attempt with inactive account', [
                'email' => $user->email,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()
            ]);
            
            return back()->withErrors([
                'email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator untuk informasi lebih lanjut.',
            ]);
        }

        // Login the user
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Log successful login
        \Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Redirect based on role
        return $this->redirectBasedOnRole($user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Redirect user based on their role
     */
    private function redirectBasedOnRole($user)
    {
        // Since we're using single dashboard approach, always redirect to /dashboard
        // The dashboard will handle role-based content rendering
        return redirect()->intended('/dashboard');
    }
}
