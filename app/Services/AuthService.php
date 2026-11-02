<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly AuditService $audit) {}

    public function attempt(string $identity, string $password, bool $remember = false): bool
    {
        $user = User::query()
            ->where('username', $identity)
            ->orWhere('email', $identity)
            ->first();

        if ($user === null || ! $user->is_active || ! Hash::check($password, $user->password)) {
            return false;
        }

        Auth::login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record('auth', 'login', $user, null, ['remember' => $remember]);

        return true;
    }

    public function logout(Request $request): void
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->audit->record('auth', 'logout', $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function changePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
        $this->audit->record('profile', 'password_changed', $user);
    }
}