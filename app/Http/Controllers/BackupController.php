<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    private const BACKUP_DIR = 'backups';

    public function index(): Response
    {
        $disk = Storage::disk('local');
        
        // Scan both direct SQLite backups and legacy zip backups if any
        $files = array_merge(
            $disk->files(self::BACKUP_DIR),
            $disk->allFiles('BeesFleur')
        );

        $backups = collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.sqlite') || str_ends_with($file, '.zip'))
            ->map(function ($file) use ($disk) {
                return [
                    'path' => $file,
                    'file_name' => basename($file),
                    'file_size' => $this->formatBytes($disk->size($file)),
                    'last_modified' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                    'type' => str_ends_with($file, '.sqlite') ? 'sqlite' : 'zip',
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        return Inertia::render('Backups/Index', [
            'backups' => $backups,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        try {
            $connection = config('database.default');
            
            if ($connection === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (! file_exists($dbPath)) {
                    return redirect()->back()->with('error', 'File database SQLite tidak ditemukan.');
                }

                $timestamp = date('Y-m-d_His');
                $backupFileName = "database-backup-{$timestamp}.sqlite";
                $disk = Storage::disk('local');
                
                // Ensure backups directory exists
                if (! $disk->exists(self::BACKUP_DIR)) {
                    $disk->makeDirectory(self::BACKUP_DIR);
                }

                $destinationAbsolutePath = $disk->path(self::BACKUP_DIR . '/' . $backupFileName);

                // Use SQLite VACUUM INTO for a safe, atomic snapshot of the live database
                try {
                    $escapedPath = str_replace("'", "''", $destinationAbsolutePath);
                    DB::connection('sqlite')->statement("VACUUM INTO '{$escapedPath}'");
                } catch (\Throwable $e) {
                    // Fallback to safe file copy if VACUUM INTO is not supported
                    copy($dbPath, $destinationAbsolutePath);
                }

                return redirect()->back()->with('success', "Backup database SQLite ({$backupFileName}) berhasil dibuat.");
            }

            // Fallback for MySQL or other DB engines
            \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
            return redirect()->back()->with('success', 'Backup database berhasil dijalankan.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menjalankan backup: ' . $e->getMessage());
        }
    }

    public function download(Request $request): StreamedResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $disk = Storage::disk('local');
        if (! $disk->exists($validated['path'])) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return $disk->download($validated['path']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $disk = Storage::disk('local');
        if ($disk->exists($validated['path'])) {
            $disk->delete($validated['path']);
            return redirect()->back()->with('success', 'File backup berhasil dihapus.');
        }

        return redirect()->back()->with('error', 'File tidak ditemukan.');
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

