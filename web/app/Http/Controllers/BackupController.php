<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(
            Backup::query()->orderByDesc('created_at')->limit(50)->get()
        );
    }

    public function store(): JsonResponse
    {
        $this->authorize('admin');

        $db = (string) config('database.connections.mysql.database');
        $user = (string) config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');

        $mysqldump = (string) config('services.mysql.dump_path', 'mysqldump');

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'backup_'.now()->format('Y-m-d_His').'.sql.gz';
        $full = $dir.DIRECTORY_SEPARATOR.$name;

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => $pass])
            ->run([
                $mysqldump,
                '--single-transaction',
                '--quick',
                '-h', $host,
                '-u', $user,
                $db,
            ]);

        if (! $result->successful()) {
            $errorDetail = $result->errorOutput() ?: $result->output();
            Log::error('Backup mysqldump failed', ['detail' => $errorDetail]);

            Backup::query()->create([
                'created_at' => now(),
                'file_path' => $name,
                'file_size' => 0,
                'backup_type' => 'manual',
                'status' => 'failed',
                'notes' => $errorDetail,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Backup failed — check server logs.',
            ], 500);
        }

        $gz = gzencode($result->output(), 9);
        if ($gz === false) {
            return response()->json(['ok' => false, 'error' => 'gzip encode failed'], 500);
        }

        file_put_contents($full, $gz);
        $size = filesize($full) ?: 0;

        Backup::query()->create([
            'created_at' => now(),
            'file_path' => 'backups/'.$name,
            'file_size' => $size,
            'backup_type' => 'manual',
            'status' => 'success',
            'notes' => null,
        ]);

        AppService::auditLog('backups', 0, 'BACKUP_CREATED', [], ['file' => $name]);

        return response()->json(['ok' => true, 'file' => $name]);
    }

    public function show(string $id): BinaryFileResponse|JsonResponse
    {
        $this->authorize('admin');
        $b = Backup::query()->find($id);
        if (! $b) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! str_starts_with($b->file_path, 'backups/')) {
            return response()->json(['error' => 'Invalid backup path'], 422);
        }

        $path = storage_path('app/'.$b->file_path);
        if (! is_file($path)) {
            return response()->json(['error' => 'File missing on disk'], 404);
        }

        return response()->download($path);
    }
}
