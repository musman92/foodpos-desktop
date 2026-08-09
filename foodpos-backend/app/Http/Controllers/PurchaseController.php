<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Account;
use App\Models\MoneySource;
use App\Support\PurchaseCatalog;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchasePaymentService;
use App\Services\PurchaseService;
use App\Support\ListingPerPage;
use App\Support\PurchaseModificationImpact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $purchases = Purchase::with(['supplier', 'branch', 'creator', 'items'])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return view('purchases.index', compact('purchases', 'perPage'));
    }

    /**
     * Show the form for creating a new purchase.
     */
    public function create()
    {
        $user = Auth::user();
        
        $purchaseCatalog = PurchaseCatalog::options($user);

        // Get suppliers
        $suppliers = Supplier::orderBy('name')->get();
        
        // Get branches
        $branches = \App\Models\Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Get Purchase account
        $purchaseAccount = Account::where('name', 'Purchase')
            ->where('type', 'expense')
            ->first();

        // Get company currency
        $company = $user->company;
        $currency = $company->currency ?? 'USD';
        $currencySymbol = get_currency_symbol($currency);

        $moneySources = MoneySource::forPayments()->where('company_id', $user->company_id)
            ->where('active', true)
            ->with('branches:id')
            ->orderBy('name')
            ->get()
            ->map(fn (MoneySource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'branch_ids' => $source->branches->pluck('id')->values()->all(),
            ])
            ->values();

        return view('purchases.create', compact(
            'purchaseCatalog',
            'suppliers',
            'branches',
            'purchaseAccount',
            'currency',
            'currencySymbol',
            'moneySources'
        ));
    }

    /**
     * Store a newly created purchase.
     */
    public function store(StorePurchaseRequest $request, PurchaseService $purchaseService, PurchasePaymentService $purchasePaymentService)
    {
        $user = Auth::user();

        try {
            $payment = $purchasePaymentService->resolve(
                $request->only(['payment_selection', 'paid_amount']),
                (float) $request->total_amount,
                (int) $user->company_id,
                (int) $request->branch_id
            );

            $purchaseData = [
                'company_id' => $user->company_id,
                'branch_id' => $request->branch_id,
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total_amount' => $request->total_amount,
                'paid_amount' => $payment['paid_amount'],
                'payment_method' => $payment['payment_method'],
                'money_source_id' => $payment['money_source_id'],
                'payment_status' => $payment['payment_status'],
                'notes' => $request->notes,
            ];

            $purchase = $purchaseService->createPurchase(
                $purchaseData,
                $request->items ?? [],
                $user->id
            );

            return redirect()
                ->route('purchases.index')
                ->with('success', 'Purchase created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error creating purchase: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified purchase.
     */
    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'branch', 'creator', 'items', 'moneySource', 'returns']);

        return view('purchases.show', compact('purchase'));
    }

    /**
     * Show the form for editing the specified purchase.
     */
    public function edit(Purchase $purchase)
    {
        $user = Auth::user();
        
        $purchaseCatalog = PurchaseCatalog::options($user);
        $suppliers = Supplier::orderBy('name')->get();
        $branches = \App\Models\Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $purchase->load(['items']);

        // Get company currency
        $company = $user->company;
        $currency = $company->currency ?? 'USD';
        $currencySymbol = get_currency_symbol($currency);

        $moneySources = MoneySource::forPayments()->where('company_id', $user->company_id)
            ->where('active', true)
            ->with('branches:id')
            ->orderBy('name')
            ->get()
            ->map(fn (MoneySource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'branch_ids' => $source->branches->pluck('id')->values()->all(),
            ])
            ->values();

        return view('purchases.edit', compact(
            'purchase',
            'purchaseCatalog',
            'suppliers',
            'branches',
            'currency',
            'currencySymbol',
            'moneySources'
        ));
    }

    /**
     * Update the specified purchase.
     */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase, PurchaseService $purchaseService, PurchasePaymentService $purchasePaymentService)
    {
        $user = Auth::user();

        try {
            $payment = $purchasePaymentService->resolve(
                $request->only(['payment_selection', 'paid_amount']),
                (float) $request->total_amount,
                (int) $user->company_id,
                (int) $request->branch_id
            );

            $purchaseData = [
                'branch_id' => $request->branch_id,
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total_amount' => $request->total_amount,
                'paid_amount' => $payment['paid_amount'],
                'payment_method' => $payment['payment_method'],
                'money_source_id' => $payment['money_source_id'],
                'payment_status' => $payment['payment_status'],
                'notes' => $request->notes,
            ];

            $purchaseService->updatePurchase(
                $purchase,
                $purchaseData,
                $request->items ?? [],
                $user->id
            );

            return redirect()
                ->route('purchases.show', $purchase)
                ->with('success', 'Purchase updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Preview impacts before updating a purchase (AJAX).
     */
    public function validateUpdate(
        UpdatePurchaseRequest $request,
        Purchase $purchase,
        PurchaseModificationImpact $impact
    ) {
        return response()->json(
            $impact->analyzeUpdate(
                $purchase,
                $request->items ?? [],
                (float) $request->total_amount
            )
        );
    }

    /**
     * Preview impacts before deleting a purchase (AJAX).
     */
    public function validateDelete(Purchase $purchase, PurchaseModificationImpact $impact)
    {
        return response()->json($impact->analyzeDelete($purchase));
    }

    /**
     * Remove the specified purchase.
     */
    public function destroy(Purchase $purchase, PurchaseService $purchaseService)
    {
        try {
            $purchaseService->deletePurchase($purchase);

            return redirect()
                ->route('purchases.index')
                ->with('success', 'Purchase deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

}
