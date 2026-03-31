<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Role::withCount('users')
            ->with('permissions')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dibuat.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->name]);

        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Cegah hapus role default sistem
        if (in_array($role->name, ['super-admin', 'admin', 'kasir', 'manager'])) {
            return redirect()->route('roles.index')
                ->with('error', 'Role sistem tidak dapat dihapus.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    /**
     * Assign role ke user.
     */
    public function assignUser(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = \App\Models\User::findOrFail($request->user_id);
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

        /** @var \App\Models\User $user */
        $user = \App\Models\User::findOrFail($request->user_id);
        $user->removeRole($role);

        return redirect()->back()
            ->with('success', "Role {$role->name} berhasil dicabut dari {$user->name}.");
    }
}
