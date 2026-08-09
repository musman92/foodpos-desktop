<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected PurchaseReturnService $purchaseReturnService,
    ) {}

    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);

        $returns = PurchaseReturn::query()
            ->with(['purchase', 'supplier', 'creator'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase-returns.index', compact('returns', 'perPage'));
    }

    public function create(Request $request)
    {
        $purchaseId = $request->integer('purchase_id') ?: null;
        $supplierId = $request->integer('supplier_id') ?: null;

        $purchases = Purchase::query()
            ->with(['supplier', 'items'])
            ->whereHas('items', function ($query) {
                $query->whereColumn('quantity_returned', '<', 'quantity');
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(function (Purchase $purchase) {
                return $purchase->items->contains(fn ($item) => $item->returnableQuantity() > 0.0001);
            })
            ->values();

        $selectedPurchase = null;
        if ($purchaseId) {
            $selectedPurchase = $purchases->firstWhere('id', $purchaseId)
                ?? Purchase::query()->with(['supplier', 'items'])->find($purchaseId);

            if ($selectedPurchase && ! $purchases->contains('id', $selectedPurchase->id)) {
                $purchases = $purchases->prepend($selectedPurchase)->values();
            }

            if ($selectedPurchase?->supplier_id) {
                $supplierId = (int) $selectedPurchase->supplier_id;
            }
        }

        $supplierIds = $purchases->pluck('supplier_id')->filter()->unique()->values();
        $suppliers = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get();

        if ($supplierId && ! $suppliers->contains('id', $supplierId)) {
            $extra = Supplier::query()->find($supplierId);
            if ($extra) {
                $suppliers = $suppliers->prepend($extra)->values();
            }
        }

        return view('purchase-returns.create', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'selectedPurchase' => $selectedPurchase,
            'selectedSupplierId' => $supplierId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateReturnPayload($request);
        $lines = $this->linesFromValidated($validated);

        $purchase = Purchase::query()->with('items')->findOrFail($validated['purchase_id']);

        try {
            $return = $this->purchaseReturnService->createReturn(
                $purchase,
                $lines,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
                $validated['return_date'],
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('purchase-returns.show', $return)
            ->with('success', 'Purchase return recorded successfully.');
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load([
            'purchase',
            'supplier',
            'creator',
            'items.purchaseItem',
            'branch',
        ]);

        return view('purchase-returns.show', [
            'purchaseReturn' => $purchaseReturn,
        ]);
    }

    public function edit(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['items', 'purchase.items', 'purchase.supplier', 'supplier']);

        $purchase = $purchaseReturn->purchase;
        if (! $purchase) {
            abort(404, 'Linked purchase not found.');
        }

        $returnedByItemId = $purchaseReturn->items
            ->groupBy('purchase_item_id')
            ->map(fn ($lines) => (float) $lines->sum('quantity'));

        return view('purchase-returns.edit', [
            'purchaseReturn' => $purchaseReturn,
            'purchase' => $purchase,
            'returnedByItemId' => $returnedByItemId,
        ]);
    }

    public function update(Request $request, PurchaseReturn $purchaseReturn)
    {
        $validated = $this->validateReturnPayload($request, requirePurchaseId: false);
        $lines = $this->linesFromValidated($validated);

        try {
            $return = $this->purchaseReturnService->updateReturn(
                $purchaseReturn,
                $lines,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
                $validated['return_date'],
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('purchase-returns.show', $return)
            ->with('success', 'Purchase return updated successfully.');
    }

    public function destroy(Request $request, PurchaseReturn $purchaseReturn)
    {
        try {
            $this->purchaseReturnService->deleteReturn(
                $purchaseReturn,
                (int) $request->user()->id,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('purchase-returns.index')
            ->with('success', 'Purchase return deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateReturnPayload(Request $request, bool $requirePurchaseId = true): array
    {
        $rules = [
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($requirePurchaseId) {
            $rules['purchase_id'] = ['required', 'integer', 'exists:purchases,id'];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{purchase_item_id: int, quantity: float, notes: ?string}>
     */
    protected function linesFromValidated(array $validated): array
    {
        return collect($validated['items'])
            ->map(fn ($line) => [
                'purchase_item_id' => (int) $line['purchase_item_id'],
                'quantity' => (float) ($line['quantity'] ?? 0),
                'notes' => $line['notes'] ?? null,
            ])
            ->filter(fn ($line) => $line['quantity'] > 0.0001)
            ->values()
            ->all();
    }
}
