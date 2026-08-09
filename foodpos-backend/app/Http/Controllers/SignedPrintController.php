<?php

namespace App\Http\Controllers;

use App\Models\KitchenKot;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\CompanyReceiptBrandingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignedPrintController extends Controller
{
    public function printJob(Request $request, int $printJob, string $token): View
    {
        $job = PrintJob::withoutGlobalScope('tenant')->findOrFail($printJob);

        if (! $job->access_token || ! hash_equals($job->access_token, $token)) {
            abort(403, 'Invalid print link.');
        }

        if ($job->created_at?->lt(now()->subDay())) {
            abort(403, 'Expired print link.');
        }

        return match ($job->document_type) {
            'kitchen_kot' => $this->renderKitchenKot((int) $job->reference_id, $request->boolean('reprint')),
            'receipt' => $this->renderReceipt((int) $job->reference_id),
            'test' => $this->renderTest((int) $job->reference_id, $request->boolean('autoprint')),
            default => abort(404, 'Unknown print document.'),
        };
    }

    public function kitchenKot(Request $request, int $kitchenKot)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired print link.');
        }

        return $this->renderKitchenKot($kitchenKot, $request->boolean('reprint'));
    }

    public function receipt(Request $request, int $order)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired print link.');
        }

        return $this->renderReceipt($order);
    }

    public function test(Request $request, int $printer)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired print link.');
        }

        return $this->renderTest($printer, $request->boolean('autoprint'));
    }

    private function renderKitchenKot(int $kitchenKotId, bool $showReprint = false): View
    {
        $kitchenKot = KitchenKot::withoutGlobalScopes(['tenant', 'branch'])
            ->findOrFail($kitchenKotId);

        $order = $kitchenKot->order()
            ->withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->with(['table', 'waiter', 'branch', 'company'])
            ->firstOrFail();

        CompanyReceiptBrandingService::applyToOrder($order);

        return view('pos.kitchen-ticket', [
            'kot' => $kitchenKot,
            'order' => $order,
            'company' => $order->company,
            'showReprint' => $showReprint,
            'showOrderCancel' => request()->boolean('cancel'),
        ]);
    }

    private function renderReceipt(int $orderId): View
    {
        $order = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->with([
                'items.menuItem',
                'items.deal.menuItems' => fn ($q) => $q->withoutGlobalScopes(),
                'cashier',
                'table',
                'branch',
                'company',
            ])
            ->findOrFail($orderId);

        CompanyReceiptBrandingService::applyToOrder($order);

        return view('pos.invoice', compact('order'));
    }

    private function renderTest(int $printerId, bool $autoPrint = false): View
    {
        $printer = Printer::withoutGlobalScopes()
            ->with(['branch.company'])
            ->findOrFail($printerId);

        return view('print.test', [
            'printer' => $printer,
            'branchName' => $printer->branch?->name ?? 'Branch',
            'companyName' => $printer->branch?->company?->name ?? config('app.name'),
            'autoPrint' => $autoPrint,
        ]);
    }
}
