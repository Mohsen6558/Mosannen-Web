<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/Profile', [
            'mustChange' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'current_password' => 'رمز عبور فعلی',
            'password' => 'رمز عبور جدید',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'رمز عبور فعلی صحیح نیست.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'event' => 'password_changed',
            'description' => 'تغییر رمز عبور',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
