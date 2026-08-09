<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        protected DatabaseBackupService $backups,
    ) {}

    public function index(): View
    {
        $this->authorizeSuperAdmin();

        return view('database-backups.index', [
            'backups' => $this->backups->listBackups(),
            'driver' => config('database.default'),
            'usesCompression' => $this->backups->usesCompression(),
            'preferNativeTools' => $this->backups->preferredNativeToolsEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $backup = $this->backups->createBackup();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('database-backups.index')
                ->with('error', 'Backup failed: '.$e->getMessage());
        }

        $method = $backup['method'] ?? 'backup';

        return redirect()
            ->route('database-backups.index')
            ->with('success', "Backup created: {$backup['filename']} ({$backup['size_label']}) via {$method}.");
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $maxKb = (int) config('database_backup.max_upload_mb', 512) * 1024;

        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'backup_file.max' => 'The backup file must not be larger than '.config('database_backup.max_upload_mb', 512).' MB.',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['backup_file'];

        try {
            $backup = $this->backups->importUploadedSqlGz($file);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('database-backups.index')
                ->with('error', 'Upload failed: '.$e->getMessage());
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', "Backup uploaded: {$backup['filename']} ({$backup['size_label']}). You can restore it from the list below.");
    }

    public function download(string $backup): BinaryFileResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $path = $this->backups->resolveBackupPath($backup);
        } catch (\Throwable $e) {
            abort(404, $e->getMessage());
        }

        return response()->download($path, basename($path));
    }

    public function restore(Request $request, string $backup): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'confirm_restore' => 'required|in:RESTORE',
        ]);

        try {
            $this->backups->restore($backup);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('database-backups.index')
                ->with('error', 'Restore failed: '.$e->getMessage());
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', 'Database restored from '.basename($backup).'. A pre-restore backup was saved automatically.');
    }

    public function destroy(string $backup): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $this->backups->delete($backup);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('database-backups.index')
                ->with('error', 'Delete failed: '.$e->getMessage());
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', 'Backup deleted: '.basename($backup).'.');
    }

    protected function authorizeSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Only platform administrators can manage database backups.');
    }
}
