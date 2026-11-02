<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LoginController extends Controller
{
    public function create(): InertiaResponse|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/login');
    }

    public function store(Request $request, AuthService $authService): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! $authService->attempt(
            $validated['login'],
            $validated['password'],
            (bool) ($validated['remember'] ?? false),
        )) {
            throw ValidationException::withMessages([
                'login' => 'Nama pengguna/email atau kata sandi tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();
        RateLimiter::clear(
            Str::transliterate(Str::lower($validated['login'])).'|'.$request->ip(),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logout($request);

        return redirect()->route('login');
    }
}
