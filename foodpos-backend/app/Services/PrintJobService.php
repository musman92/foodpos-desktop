<?php

namespace App\Services;

use App\Events\PrintJobCreated;
use App\Models\KitchenKot;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class PrintJobService
{
    public function hasDirectPrinters(int $branchId, string $role): bool
    {
        return Printer::withoutGlobalScope('tenant')
            ->where('branch_id', $branchId)
            ->where('role', $role)
            ->where('printing_mode', Printer::MODE_DIRECT)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Queue kitchen KOT slips for configured printers.
     *
     * @param  list<KitchenKot>  $kots
     * @return array{browser_kot_ids: list<int>, desktop_jobs: int}
     */
    public function queueKitchenKots(int $branchId, array $kots, bool $asReprint = false, bool $directOnly = false, bool $asOrderCancel = false): array
    {
        $printers = $this->activePrintersForBranch($branchId, 'kitchen');
        if ($directOnly) {
            $printers = $printers->filter(fn (Printer $p) => $p->printing_mode === Printer::MODE_DIRECT)->values();
        }

        $browserKotIds = [];
        $desktopJobs = 0;

        if ($printers->isEmpty()) {
            return [
                'browser_kot_ids' => $directOnly
                    ? []
                    : collect($kots)->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'desktop_jobs' => 0,
            ];
        }

        $hasBrowserPrinter = ! $directOnly
            && $printers->contains(fn (Printer $p) => $p->printing_mode === Printer::MODE_BROWSER_POPUP);

        foreach ($kots as $kot) {
            if ($hasBrowserPrinter) {
                $browserKotIds[] = (int) $kot->id;
            }

            foreach ($printers as $printer) {
                if ($printer->printing_mode !== Printer::MODE_DIRECT) {
                    continue;
                }

                $job = $this->createJob(
                    printer: $printer,
                    documentType: 'kitchen_kot',
                    referenceType: KitchenKot::class,
                    referenceId: (int) $kot->id,
                    deviceName: $printer->device_name,
                );

                $query = [];
                if ($asReprint) {
                    $query['reprint'] = '1';
                }
                if ($asOrderCancel) {
                    $query['cancel'] = '1';
                }
                if ($query !== []) {
                    $job->update(['print_url' => $job->print_url.'?'.http_build_query($query)]);
                }

                $desktopJobs++;
            }
        }

        return [
            'browser_kot_ids' => $browserKotIds,
            'desktop_jobs' => $desktopJobs,
        ];
    }

    /**
     * Queue receipt print for configured printers.
     *
     * @return array{browser_print: bool, desktop_jobs: int}
     */
    public function queueReceipt(Order $order): array
    {
        $printers = $this->activePrintersForBranch((int) $order->branch_id, 'receipt');
        $desktopJobs = 0;
        $browserPrint = false;

        if ($printers->isEmpty()) {
            return ['browser_print' => true, 'desktop_jobs' => 0];
        }

        foreach ($printers as $printer) {
            if ($printer->printing_mode === 'browser') {
                $browserPrint = true;

                continue;
            }

            $this->createJob(
                printer: $printer,
                documentType: 'receipt',
                referenceType: Order::class,
                referenceId: (int) $order->id,
                deviceName: $printer->device_name,
            );
            $desktopJobs++;
        }

        return [
            'browser_print' => $browserPrint,
            'desktop_jobs' => $desktopJobs,
        ];
    }

    public function signedKitchenKotUrl(KitchenKot $kot, bool $reprint = false): string
    {
        $params = ['kitchenKot' => $kot->id];
        if ($reprint) {
            $params['reprint'] = 1;
        }

        return URL::temporarySignedRoute(
            'print.kitchen-kot',
            now()->addHour(),
            $params,
        );
    }

    public function signedReceiptUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'print.receipt',
            now()->addHour(),
            ['order' => $order->id],
        );
    }

    public function signedTestPrintUrl(Printer $printer, bool $autoPrint = false): string
    {
        $params = ['printer' => $printer->id];
        if ($autoPrint) {
            $params['autoprint'] = 1;
        }

        return URL::temporarySignedRoute(
            'print.test',
            now()->addHour(),
            $params,
        );
    }

    /**
     * Queue a test print job for a direct-print printer.
     */
    public function queueTestPrint(Printer $printer): PrintJob
    {
        return $this->createJob(
            printer: $printer,
            documentType: 'test',
            referenceType: Printer::class,
            referenceId: (int) $printer->id,
            deviceName: $printer->device_name,
        );
    }

    public function printJobUrl(PrintJob $job, bool $autoPrint = false): string
    {
        $url = $this->absolutePrintJobUrl($job);

        if ($autoPrint) {
            $url .= (str_contains($url, '?') ? '&' : '?').'autoprint=1';
        }

        return $url;
    }

    /**
     * @return Collection<int, PrintJob>
     */
    public function pendingJobsForBranch(int $branchId, int $limit = 50): Collection
    {
        return PrintJob::withoutGlobalScope('tenant')
            ->with('printer')
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, PrintJob>
     */
    public function recentJobsForBranch(int $branchId, int $limit = 5): Collection
    {
        return PrintJob::withoutGlobalScope('tenant')
            ->with('printer')
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Printer>
     */
    public function desktopPrintersForBranch(int $branchId): Collection
    {
        return Printer::withoutGlobalScope('tenant')
            ->where('branch_id', $branchId)
            ->where('printing_mode', Printer::MODE_DIRECT)
            ->where('is_active', true)
            ->orderBy('role')
            ->orderBy('title')
            ->get();
    }

    public function acknowledge(int $jobId, int $branchId, string $status, ?string $errorMessage = null): ?PrintJob
    {
        $job = PrintJob::withoutGlobalScope('tenant')
            ->where('id', $jobId)
            ->where('branch_id', $branchId)
            ->first();

        if (! $job) {
            return null;
        }

        $job->update([
            'status' => $status === 'printed' ? 'printed' : 'failed',
            'error_message' => $errorMessage,
            'printed_at' => $status === 'printed' ? now() : null,
            'acked_at' => now(),
        ]);

        return $job->fresh();
    }

    /**
     * @return Collection<int, Printer>
     */
    private function activePrintersForBranch(int $branchId, string $role): Collection
    {
        return Printer::withoutGlobalScope('tenant')
            ->where('branch_id', $branchId)
            ->where('role', $role)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();
    }

    private function createJob(
        Printer $printer,
        string $documentType,
        string $referenceType,
        int $referenceId,
        ?string $deviceName,
    ): PrintJob {
        $token = PrintJob::generateAccessToken();

        $job = PrintJob::create([
            'company_id' => $printer->company_id,
            'branch_id' => $printer->branch_id,
            'printer_id' => $printer->id,
            'document_type' => $documentType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'access_token' => $token,
            'print_url' => '',
            'device_name' => $deviceName,
            'status' => 'pending',
        ]);

        $job->update([
            'print_url' => $this->absolutePrintJobUrl($job),
        ]);

        PrintJobCreated::dispatch($job->fresh());

        return $job->fresh();
    }

    private function absolutePrintJobUrl(PrintJob $job): string
    {
        $base = rtrim($this->appBaseUrl(), '/');

        return $base.'/print/job/'.$job->id.'/'.$job->access_token;
    }

    private function appBaseUrl(): string
    {
        if (! app()->runningInConsole()) {
            $host = request()->getSchemeAndHttpHost();
            if ($host !== '') {
                return $host;
            }
        }

        return rtrim((string) config('app.url'), '/');
    }
}
