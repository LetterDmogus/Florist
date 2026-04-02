<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        $disk = Storage::disk('local');
        $backupName = 'BeesFleur';
        $files = $disk->allFiles($backupName);

        $backups = collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(function ($file) use ($disk) {
                return [
                    'path' => $file,
                    'file_name' => basename($file),
                    'file_size' => $this->formatBytes($disk->size($file)),
                    'last_modified' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        return Inertia::render('Backups/Index', [
            'backups' => $backups,
        ]);
    }

    public function create(Request $request)
    {
        // For security and performance, we can choose to backup only DB or Full
        // For manual triggered from UI, let's do only DB first or provide option
        try {
            Artisan::call('backup:run', ['--only-db' => true]);
            return redirect()->back()->with('success', 'Backup database berhasil dijalankan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menjalankan backup: ' . $e->getMessage());
        }
    }

    public function download(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $disk = Storage::disk('local');
        if (!$disk->exists($validated['path'])) {
            abort(404);
        }

        return $disk->download($validated['path']);
    }

    public function destroy(Request $request)
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

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
