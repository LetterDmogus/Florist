<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function resolvePerPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->input('per_page', $default);
        if (! in_array($perPage, [10, 20, 30], true)) {
            $perPage = $default;
        }

        return $perPage;
    }

    protected function resolveSort(
        Request $request,
        array $allowedColumns,
        string $defaultBy = 'created_at',
        string $defaultDir = 'desc',
    ): array {
        $sortBy = (string) $request->input('sort_by', $defaultBy);
        if (! in_array($sortBy, $allowedColumns, true)) {
            $sortBy = $defaultBy;
        }

        $sortDir = strtolower((string) $request->input('sort_dir', $defaultDir));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = $defaultDir;
        }

        return [$sortBy, $sortDir];
    }
}
