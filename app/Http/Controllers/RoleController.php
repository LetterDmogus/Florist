<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = [
        'super-admin',
        'admin',
        'kasir',
        'manager',
    ];

    public function index(Request $request): Response
    {
        $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $modelMorphKey = config('permission.column_names.model_morph_key') ?: 'model_id';
        $modelHasRolesTable = config('permission.table_names.model_has_roles');

        $roleUserCounts = DB::table($modelHasRolesTable.' as mhr')
            ->join('users', 'users.id', '=', 'mhr.'.$modelMorphKey)
            ->select('mhr.'.$rolePivotKey, DB::raw('count(*) as total'))
            ->where('mhr.model_type', User::class)
            ->whereNull('users.deleted_at')
            ->groupBy('mhr.'.$rolePivotKey)
            ->pluck('total', 'mhr.'.$rolePivotKey);

        $roles = Role::query()
            ->with('permissions:id,name')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => (int) ($roleUserCounts[$role->id] ?? 0),
                'is_system' => in_array($role->name, self::SYSTEM_ROLES, true),
                'deleted_at' => $role->deleted_at?->toIso8601String(),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $request->only('search', 'trashed'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            if (! empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }
        });

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dibuat.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        if (in_array($role->name, self::SYSTEM_ROLES, true) && $request->name !== $role->name) {
            return redirect()->route('roles.index')
                ->with('error', 'Role sistem tidak dapat diubah namanya.');
        }

        DB::transaction(function () use ($role, $validated): void {
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($validated['permissions'] ?? []);
        });

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Cegah hapus role default sistem
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return redirect()->route('roles.index')
                ->with('error', 'Role sistem tidak dapat dihapus.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $role = Role::withTrashed()->findOrFail($id);

        $role->restore();

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $role = Role::withTrashed()->findOrFail($id);

        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return redirect()->route('roles.index')
                ->with('error', 'Role sistem tidak dapat dihapus permanen.');
        }

        $role->forceDelete();

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dihapus permanen.');
    }

    /**
     * Assign role ke user.
     */
    public function assignUser(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = User::findOrFail($request->user_id);
        $user->assignRole($role);

        return redirect()->back()
            ->with('success', "Role {$role->name} berhasil di-assign ke {$user->name}.");
    }

    /**
     * Cabut role dari user.
     */
    public function revokeUser(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = User::findOrFail($request->user_id);
        $user->removeRole($role);

        return redirect()->back()
            ->with('success', "Role {$role->name} berhasil dicabut dari {$user->name}.");
    }
}
