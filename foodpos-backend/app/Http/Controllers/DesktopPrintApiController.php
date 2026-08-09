<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchDesktopKey;
use App\Services\DesktopCommandService;
use App\Services\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

class DesktopPrintApiController extends Controller
{
    public function __construct(
        protected PrintJobService $printJobService,
        protected DesktopCommandService $desktopCommandService,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('desktop_branch_id');
        /** @var BranchDesktopKey $desktopKey */
        $desktopKey = $request->attributes->get('branch_desktop_key');

        $branch = Branch::withoutGlobalScope('tenant')->find($branchId);
        $printers = $this->printJobService->desktopPrintersForBranch($branchId);
        $recentJobs = $this->printJobService->recentJobsForBranch($branchId, 5);

        return response()->json([
            'branch' => [
                'id' => $branchId,
                'name' => $branch?->name ?? 'Branch',
            ],
            'desktop_key' => [
                'id' => $desktopKey->id,
                'name' => $desktopKey->name,
                'connection_code' => $desktopKey->connection_code,
            ],
            'direct_printer_count' => $printers->count(),
            'printer_settings_url' => rtrim(config('app.url'), '/').'/printer-settings?branch_id='.$branchId,
            'printers' => $printers->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'role' => $p->role,
                'device_name' => $p->device_name,
                'is_default' => $p->is_default,
            ])->values(),
            'recent_jobs' => $recentJobs->map(fn ($job) => [
                'id' => $job->id,
                'document_type' => $job->document_type,
                'status' => $job->status,
                'device_name' => $job->device_name,
                'printer_title' => $job->printer?->title,
                'error_message' => $job->error_message,
                'created_at' => $job->created_at?->toIso8601String(),
                'printed_at' => $job->printed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('desktop_branch_id');
        /** @var BranchDesktopKey $desktopKey */
        $desktopKey = $request->attributes->get('branch_desktop_key');
        $branch = Branch::withoutGlobalScope('tenant')->find($branchId);

        return response()->json([
            'branch_id' => $branchId,
            'branch_name' => $branch?->name ?? 'Branch',
            'desktop_key_id' => $desktopKey->id,
            'desktop_key_name' => $desktopKey->name,
            'channel' => 'private-branch.'.$branchId.'.print-jobs',
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
            ],
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var BranchDesktopKey $desktopKey */
        $desktopKey = $request->attributes->get('branch_desktop_key');

        $validated = $request->validate([
            'connection_code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $desktopKey->forceFill([
            'connection_code' => $validated['connection_code'],
            'last_heartbeat_at' => now(),
        ])->save();

        $commands = $this->desktopCommandService->pullPending($desktopKey->id);

        return response()->json([
            'ok' => true,
            'connection_code' => $desktopKey->connection_code,
            'commands' => array_map(fn (string $type) => ['type' => $type], $commands),
        ]);
    }

    public function systemPrinters(Request $request): JsonResponse
    {
        /** @var BranchDesktopKey $desktopKey */
        $desktopKey = $request->attributes->get('branch_desktop_key');

        $validated = $request->validate([
            'printers' => ['required', 'array', 'max:100'],
            'printers.*.name' => ['required', 'string', 'max:255'],
            'printers.*.display_name' => ['nullable', 'string', 'max:255'],
            'printers.*.is_default' => ['nullable', 'boolean'],
        ]);

        $printers = collect($validated['printers'])
            ->map(fn (array $printer) => [
                'name' => $printer['name'],
                'display_name' => $printer['display_name'] ?? $printer['name'],
                'is_default' => (bool) ($printer['is_default'] ?? false),
            ])
            ->values()
            ->all();

        $desktopKey->forceFill([
            'system_printers' => $printers,
            'system_printers_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'count' => count($printers),
        ]);
    }

    public function broadcastingAuth(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('desktop_branch_id');
        $channelName = (string) $request->input('channel_name', '');
        $socketId = (string) $request->input('socket_id', '');

        if ($socketId === '' || $channelName === '') {
            return response()->json(['message' => 'Missing socket_id or channel_name.'], 422);
        }

        $expectedChannel = 'private-branch.'.$branchId.'.print-jobs';
        if ($channelName !== $expectedChannel) {
            return response()->json(['message' => 'Unauthorized channel.'], 403);
        }

        $pusher = new Pusher(
            config('broadcasting.connections.reverb.key'),
            config('broadcasting.connections.reverb.secret'),
            config('broadcasting.connections.reverb.app_id'),
            [
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
                'useTLS' => config('broadcasting.connections.reverb.options.useTLS', true),
            ]
        );

        $auth = json_decode($pusher->authorizeChannel($channelName, $socketId), true);

        return response()->json($auth);
    }

    public function pendingJobs(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('desktop_branch_id');
        $jobs = $this->printJobService->pendingJobsForBranch($branchId);

        return response()->json([
            'jobs' => $jobs->map(fn ($job) => [
                'id' => $job->id,
                'document_type' => $job->document_type,
                'print_url' => $job->print_url,
                'device_name' => $job->device_name,
                'printer_id' => $job->printer_id,
                'printer_title' => $job->printer?->title,
                'created_at' => $job->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function acknowledge(Request $request, int $printJob): JsonResponse
    {
        $branchId = (int) $request->attributes->get('desktop_branch_id');

        $validated = $request->validate([
            'status' => ['required', 'in:printed,failed'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $job = $this->printJobService->acknowledge(
            $printJob,
            $branchId,
            $validated['status'],
            $validated['error_message'] ?? null,
        );

        if (! $job) {
            return response()->json(['message' => 'Print job not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
            ],
        ]);
    }
}
