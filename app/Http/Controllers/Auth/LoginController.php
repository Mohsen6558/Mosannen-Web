<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ], [], [
            'username' => 'نام کاربری',
            'password' => 'رمز عبور',
        ]);

        // Throttle per username+IP so one account cannot be brute forced and
        // one noisy client cannot lock out the whole clinic.
        $key = mb_strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'تعداد تلاش‌های ناموفق زیاد است. %d ثانیه دیگر دوباره تلاش کنید.',
                    RateLimiter::availableIn($key),
                ),
            ]);
        }

        $attempt = Auth::attempt([
            'username' => mb_strtolower($credentials['username']),
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $attempt) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور صحیح نیست.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'event' => 'login',
            'description' => 'ورود به سامانه',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        // A migrated account still carries the random password the importer
        // set; it cannot proceed until a real one is chosen.
        if ($user->must_change_password) {
            return redirect()->route('profile.edit')
                ->with('warning', 'برای ادامه باید رمز عبور خود را تغییر دهید.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
