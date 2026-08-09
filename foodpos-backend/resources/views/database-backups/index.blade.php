@extends('layouts.app')

@section('title', 'Database Backups')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Database Backups</h1>
            <p class="mt-1 text-sm text-gray-500">
                Create, download, and restore full database backups. Only super administrators can access this page.
            </p>
            <p class="mt-2 text-xs text-gray-400">
                Current driver: <span class="font-semibold uppercase">{{ $driver }}</span>
                · Compression: <span class="font-semibold">{{ $usesCompression ? 'On (.sql.gz)' : 'Off (.sql)' }}</span>
                · Native tools: <span class="font-semibold">{{ $preferNativeTools ? 'Preferred when available' : 'Disabled' }}</span>
            </p>
            <p class="mt-2 text-xs text-gray-500">
                If <code class="font-mono">mysqldump</code> is not installed (common on local Mac/Windows PHP setups), the app automatically uses a built-in PHP exporter instead.
                Large databases are exported in chunks and compressed to reduce disk usage.
            </p>
        </div>

        <form action="{{ route('database-backups.store') }}" method="POST"
              onsubmit="return confirm('Create a new full database backup now?');">
            @csrf
            <button type="submit"
                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700">
                <i class="fas fa-database mr-2"></i>
                Create backup
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900">Upload backup</h2>
        <p class="mt-1 text-sm text-gray-500">
            Add a previously downloaded <span class="font-mono text-xs">.sql.gz</span> file to the backup list so you can restore it from this page.
            @if(! in_array($driver, ['mysql', 'pgsql'], true))
                <span class="block mt-2 text-amber-700">SQL uploads are available when the app uses MySQL or PostgreSQL. Current driver: <span class="font-semibold uppercase">{{ $driver }}</span>.</span>
            @endif
        </p>
        <form action="{{ route('database-backups.upload') }}" method="POST" enctype="multipart/form-data" class="mt-4 flex flex-col sm:flex-row sm:items-end gap-3">
            @csrf
            <div class="flex-1">
                <label for="backup_file" class="block text-sm font-medium text-gray-700 mb-2">Backup file</label>
                <input type="file"
                       name="backup_file"
                       id="backup_file"
                       accept=".sql.gz,application/gzip,application/x-gzip"
                       @disabled(! in_array($driver, ['mysql', 'pgsql'], true))
                       required
                       class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 @error('backup_file') border-red-500 @enderror">
                @error('backup_file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-400">Max size: {{ config('database_backup.max_upload_mb', 512) }} MB. File must end with <span class="font-mono">.sql.gz</span>.</p>
            </div>
            <button type="submit"
                    @disabled(! in_array($driver, ['mysql', 'pgsql'], true))
                    class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-white border border-gray-300 text-gray-800 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-upload mr-2"></i>
                Upload backup
            </button>
        </form>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Before you restore</p>
        <p class="mt-1">Restore replaces the entire database with the selected backup. A pre-restore backup is created automatically, but you should still download important backups before restoring.</p>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Saved backups</h2>
        </div>

        @if($backups->isEmpty())
            <div class="px-6 py-12 text-center text-gray-500">
                <i class="fas fa-database text-4xl text-gray-300 mb-4"></i>
                <p>No backups yet. Click <strong>Create backup</strong> to save the current database.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Driver</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($backups as $backup)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                    {{ $backup['created_at']->format('M d, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 font-mono text-xs break-all">
                                    {{ $backup['filename'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-800 uppercase">
                                        {{ $backup['driver'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                    {{ $backup['method'] ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right tabular-nums text-gray-700">
                                    {{ $backup['size_label'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('database-backups.download', $backup['filename']) }}"
                                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                            <i class="fas fa-download mr-1"></i>
                                            Download
                                        </a>

                                        <button type="button"
                                                data-restore-filename="{{ $backup['filename'] }}"
                                                class="restore-backup-trigger inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-800 hover:bg-amber-100">
                                            <i class="fas fa-undo mr-1"></i>
                                            Restore
                                        </button>

                                        <form action="{{ route('database-backups.destroy', $backup['filename']) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm(@json('Delete backup '.$backup['filename'].'?'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100">
                                                <i class="fas fa-trash mr-1"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('modals')
<div id="restore-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="restore-modal-title">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 id="restore-modal-title" class="text-lg font-semibold text-gray-900">Restore database</h3>
            <p class="mt-1 text-sm text-gray-500">This will replace the entire database with the selected backup.</p>
        </div>
        <form id="restore-form" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Warning</p>
                <p class="mt-1">All current data will be overwritten. A pre-restore backup will be created automatically.</p>
                <p class="mt-2 font-mono text-xs break-all" id="restore-filename"></p>
            </div>
            <div>
                <label for="confirm_restore" class="block text-sm font-medium text-gray-700 mb-2">
                    Type <span class="font-mono font-semibold">RESTORE</span> to confirm
                </label>
                <input type="text"
                       name="confirm_restore"
                       id="confirm_restore"
                       required
                       autocomplete="off"
                       class="block w-full h-11 px-4 rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                        data-restore-cancel
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    Restore database
                </button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
(function () {
    const restoreUrlTemplate = @json(route('database-backups.restore', ['backup' => '__BACKUP__']));
    const modal = document.getElementById('restore-modal');
    const form = document.getElementById('restore-form');
    const filenameEl = document.getElementById('restore-filename');
    const confirmInput = document.getElementById('confirm_restore');

    function openRestoreModal(filename) {
        if (!modal || !form || !filenameEl || !confirmInput) {
            return;
        }

        filenameEl.textContent = filename;
        form.action = restoreUrlTemplate.replace('__BACKUP__', encodeURIComponent(filename));
        confirmInput.value = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        confirmInput.focus();
    }

    function closeRestoreModal() {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('.restore-backup-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            const filename = button.getAttribute('data-restore-filename');
            if (filename) {
                openRestoreModal(filename);
            }
        });
    });

    document.querySelectorAll('[data-restore-cancel]').forEach(function (button) {
        button.addEventListener('click', closeRestoreModal);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeRestoreModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeRestoreModal();
        }
    });
})();
</script>
@endpush
@endsection
