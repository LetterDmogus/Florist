<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['created_at', 'description', 'event', 'subject_type', 'subject_id', 'causer_id'],
            'created_at',
            'desc',
        );

        $activities = Activity::with(['causer', 'subject'])
            ->when($request->search, function ($query, $search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%");
            })
            ->when($request->causer_id, fn ($q, $causer) => $q->where('causer_id', $causer))
            ->orderBy($sortBy, $sortDir)
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Logs/Index', [
            'activities' => $activities,
            'users' => $users,
            'filters' => $request->only(['search', 'causer_id', 'sort_by', 'sort_dir']),
        ]);
    }
}
