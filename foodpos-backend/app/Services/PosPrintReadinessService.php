<?php

namespace App\Services;

use App\Models\BranchDesktopKey;
use App\Models\Printer;
use Illuminate\Http\JsonResponse;

class PosPrintReadinessService
{
    public function __construct(private PrinterVerificationService $printerVerification) {}

    /**
     * @param  list<'kitchen'|'receipt'>  $needs
     * @return array{ok: bool, errors: list<string>, warnings: list<string>}
     */
    public function check(int $branchId, array $needs): array
    {
        $errors = [];
        $warnings = [];

        foreach ($needs as $role) {
            $printers = Printer::withoutGlobalScope('tenant')
                ->where('branch_id', $branchId)
                ->where('role', $role)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->get();

            if ($printers->isEmpty()) {
                continue;
            }

            $directPrinters = $printers->filter(fn (Printer $p) => $p->printing_mode === Printer::MODE_DIRECT);

            if ($directPrinters->isNotEmpty() && ! $this->branchDesktopOnline($branchId)) {
                $errors[] = 'Desktop print app is not connected. Open theFoodPOS Print APP on the branch PC, or switch printers to browser popup mode.';
            }

            foreach ($directPrinters as $printer) {
                $verify = $this->printerVerification->verify($printer);

                if (! $verify['ok']) {
                    $errors[] = "{$printer->title}: {$verify['message']}";
                }
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<'kitchen'|'receipt'>  $needs
     */
    public function readinessErrorResponse(int $branchId, array $needs): ?JsonResponse
    {
        $result = $this->check($branchId, $needs);

        if ($result['ok']) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $result['errors'][0] ?? 'Printing is not ready.',
            'errors' => $result['errors'],
        ], 422);
    }

    private function branchDesktopOnline(int $branchId): bool
    {
        return BranchDesktopKey::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->contains(fn (BranchDesktopKey $key) => $key->isOnline());
    }
}
