<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('logs.view'), 403);

        $database = $this->checkDatabase();
        $cache = $this->checkCache();
        $queue = $this->checkQueue();

        $checks = [$database, $cache, $queue];
        $ok = collect($checks)->every(fn (array $result): bool => (bool) ($result['ok'] ?? false));

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $database,
                'cache' => $cache,
                'queue' => $queue,
            ],
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return [
                'ok' => true,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $cacheKey = 'healthcheck:'.now()->timestamp.':'.random_int(1000, 9999);

        try {
            Cache::put($cacheKey, 'ok', now()->addMinute());
            $value = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            return [
                'ok' => $value === 'ok',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $connection = (string) config('queue.default', 'sync');
            $queueName = (string) config("queue.connections.{$connection}.queue", 'default');
            $pending = Queue::connection($connection)->size($queueName);
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            return [
                'ok' => true,
                'connection' => $connection,
                'queue' => $queueName,
                'pending_jobs' => (int) $pending,
                'failed_jobs' => (int) $failed,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
