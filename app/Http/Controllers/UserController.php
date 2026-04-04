<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = $this->resolvePerPage($request);
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['name', 'email', 'created_at', 'updated_at'],
            'name',
            'asc',
        );

        $users = User::query()
            ->with('roles:id,name')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when(
                $request->filled('role'),
                fn ($q) => $q->role($request->string('role')->toString())
            )
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'primary_role' => $user->roles->first()?->name,
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => $user->created_at?->format('d M Y H:i'),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
            ]);

        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                ...$request->only('search', 'role', 'trashed', 'sort_by', 'sort_dir'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->syncRoles([$validated['role_name']]);
        });

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (
            $user->hasRole('super-admin') &&
            $validated['role_name'] !== 'super-admin' &&
            User::role('super-admin')->count() <= 1
        ) {
            return redirect()->route('users.index')
                ->with('error', 'Minimal harus ada satu user dengan role super-admin.');
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($user, $payload, $validated): void {
            $user->update($payload);
            $user->syncRoles([$validated['role_name']]);
        });

        return redirect()->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ((int) auth()->id() === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'Akun yang sedang login tidak bisa dihapus dari menu ini.');
        }

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Super-admin terakhir tidak bisa dihapus.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if ((int) auth()->id() === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'Akun yang sedang login tidak bisa dihapus permanen.');
        }

        if ($user->hasRole('super-admin') && User::withTrashed()->role('super-admin')->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Super-admin terakhir tidak bisa dihapus permanen.');
        }

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->forceDelete();
        });

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dihapus permanen.');
    }
}
