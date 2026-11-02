<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:all,ketua,bendahara'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);
        $query = User::query()->latest('id');
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return Inertia::render('users/index', [
            'users' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        return DB::transaction(function () use ($data): RedirectResponse {
            $user = User::query()->create([
                ...$data,
                'is_active' => true,
            ]);
            $this->audit->record('users', 'created', $user, null, [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ]);

            return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil dibuat.');
        });
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('users/edit', [
            'user' => $user->only(['id', 'name', 'username', 'email', 'phone', 'role', 'is_active']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        return DB::transaction(function () use ($data, $user): RedirectResponse {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $old = [
                'name' => $locked->name,
                'username' => $locked->username,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'role' => $locked->role->value,
            ];
            $wasActiveChair = $locked->is_active && $locked->isKetua();
            $willRemainActiveChair = $locked->is_active && $data['role'] === UserRole::Ketua->value;
            if ($wasActiveChair && ! $willRemainActiveChair) {
                $this->ensureAnotherActiveChairExists($locked);
            }

            $passwordChanged = filled($data['password'] ?? null);
            if (! $passwordChanged) {
                unset($data['password']);
            }
            $locked->update($data);
            $this->audit->record('users', 'updated', $locked, $old, [
                'name' => $locked->name,
                'username' => $locked->username,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'role' => $locked->role->value,
                'password_reset' => $passwordChanged,
            ]);

            return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui.');
        });
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        return DB::transaction(function () use ($user): RedirectResponse {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $locked->is_active) {
                throw ValidationException::withMessages(['user' => 'Akun sudah nonaktif.']);
            }
            if ($locked->isKetua()) {
                $this->ensureAnotherActiveChairExists($locked);
            }

            $locked->update(['is_active' => false]);
            $this->audit->record('users', 'deactivated', $locked, ['is_active' => true], ['is_active' => false]);

            return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil dinonaktifkan.');
        });
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        return DB::transaction(function () use ($user): RedirectResponse {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->is_active) {
                throw ValidationException::withMessages(['user' => 'Akun sudah aktif.']);
            }

            $locked->update(['is_active' => true]);
            $this->audit->record('users', 'activated', $locked, ['is_active' => false], ['is_active' => true]);

            return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil diaktifkan.');
        });
    }

    private function ensureAnotherActiveChairExists(User $user): void
    {
        $anotherChairExists = User::query()
            ->whereKeyNot($user->id)
            ->where('role', UserRole::Ketua)
            ->where('is_active', true)
            ->lockForUpdate()
            ->exists();

        if (! $anotherChairExists) {
            throw ValidationException::withMessages([
                'user' => 'Tidak dapat menonaktifkan atau menurunkan peran Ketua aktif terakhir.',
            ]);
        }
    }
}