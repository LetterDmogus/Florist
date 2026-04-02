<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roleNames = $user
            ? $user->roles()->pluck('name')->values()->all()
            : [];
        $isSuperAdmin = in_array('super-admin', $roleNames, true);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'roles' => $roleNames,
                    'permissions' => $isSuperAdmin
                        ? ['*']
                        : $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            'roles_list' => $isSuperAdmin
                ? Role::orderBy('name')->get(['id', 'name'])
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
