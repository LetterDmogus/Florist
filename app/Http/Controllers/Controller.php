<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
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
