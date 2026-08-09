<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MoneySource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Services\SupplierPaymentService;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $supplierPaymentService
    ) {}

    /**
     * Display a listing of supplier payments.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();
        
        $payments = SupplierPayment::with(['supplier', 'branch', 'account', 'moneySource', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return view('supplier-payments.index', compact('payments', 'perPage'));
    }

    /**
     * Show the form for creating a new supplier payment.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        
        // Get suppliers
        $suppliers = Supplier::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        // Get accounts (for payment method)
        $accounts = Account::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Get branches
        $branches = \App\Models\Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        $selectedSupplierId = old('supplier_id', $request->get('supplier_id'));
        $selectedBranchId = old('branch_id', $request->get('branch_id'));
        $unpaidPurchases = collect();
        $totalPending = 0;
        
        if ($selectedSupplierId) {
            // Get unpaid purchases for selected supplier
            $unpaidPurchasesQuery = Purchase::where('supplier_id', $selectedSupplierId)
                ->where(function($query) {
                    $query->where('payment_status', 'pending')
                          ->orWhere(function($q) {
                              $q->where('payment_status', 'partial')
                                ->whereRaw('paid_amount < total_amount');
                          });
                });
            
            // Filter by branch if selected
            if ($selectedBranchId) {
                $unpaidPurchasesQuery->where('branch_id', $selectedBranchId);
            }
            
            $unpaidPurchases = $unpaidPurchasesQuery
                ->orderBy('purchase_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            $totalPending = $unpaidPurchases->sum(function($purchase) {
                return max(0, (float) $purchase->total_amount - (float) ($purchase->returned_amount ?? 0) - (float) ($purchase->paid_amount ?? 0));
            });
        }

        $moneySources = $this->moneySourcesForBranch((int) $user->company_id, $selectedBranchId ? (int) $selectedBranchId : null);

        return view('supplier-payments.create', compact(
            'suppliers',
            'accounts',
            'branches',
            'moneySources',
            'selectedSupplierId',
            'selectedBranchId',
            'unpaidPurchases',
            'totalPending'
        ));
    }

    /**
     * Store a newly created supplier payment.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'account_id' => 'required|exists:accounts,id',
            'money_source_id' => 'required|exists:money_sources,id',
            'payment_date' => 'required|date',
            'total_amount' => 'nullable|numeric|min:0',
            'purchase_amounts' => 'nullable|array',
            'purchase_amounts.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ], [
            'purchase_amounts.*.numeric' => 'Payment amount must be a valid number.',
            'purchase_amounts.*.min' => 'Payment amount cannot be negative.',
        ]);

        // Get unpaid purchases
        $unpaidPurchasesQuery = Purchase::where('supplier_id', $request->supplier_id)
            ->where(function($query) {
                $query->where('payment_status', 'pending')
                      ->orWhere(function($q) {
                          $q->where('payment_status', 'partial')
                            ->whereRaw('paid_amount < total_amount');
                      });
            });
        
        // Filter by branch if provided
        if ($request->branch_id) {
            $unpaidPurchasesQuery->where('branch_id', $request->branch_id);
        }
        
        $unpaidPurchases = $unpaidPurchasesQuery
            ->orderBy('purchase_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($unpaidPurchases->isEmpty()) {
            return back()->withErrors(['supplier_id' => 'No unpaid purchases found for this supplier.']);
        }

        $purchaseAmounts = [];
        $totalPaymentAmount = 0;

        // Check if user provided total amount or individual amounts
        if ($request->filled('total_amount')) {
            // Pay oldest purchases first
            $remainingAmount = $request->total_amount;
            
            foreach ($unpaidPurchases as $purchase) {
                if ($remainingAmount <= 0) {
                    break;
                }
                
                $pendingAmount = max(0, (float) $purchase->total_amount - (float) ($purchase->returned_amount ?? 0) - (float) ($purchase->paid_amount ?? 0));
                $paymentAmount = min($remainingAmount, $pendingAmount);
                
                if ($paymentAmount > 0) {
                    $purchaseAmounts[$purchase->id] = $paymentAmount;
                    $totalPaymentAmount += $paymentAmount;
                    $remainingAmount -= $paymentAmount;
                }
            }
            
            // Allow some tolerance for floating point differences
            if (abs($totalPaymentAmount - $request->total_amount) > 0.01) {
                if ($totalPaymentAmount < $request->total_amount) {
                    return back()->withInput()->withErrors(['total_amount' => 'Payment amount (' . number_format($request->total_amount, 2) . ') exceeds total pending amount (' . number_format($totalPaymentAmount, 2) . ').']);
                }
            }
        } elseif ($request->filled('purchase_amounts')) {
            // Use individual amounts
            foreach ($request->purchase_amounts as $purchaseId => $amount) {
                if ($amount > 0) {
                    $purchase = $unpaidPurchases->firstWhere('id', $purchaseId);
                    if (!$purchase) {
                        continue;
                    }
                    
                    $pendingAmount = max(0, (float) $purchase->total_amount - (float) ($purchase->returned_amount ?? 0) - (float) ($purchase->paid_amount ?? 0));
                    if ($amount > $pendingAmount) {
                        return back()->withInput()->withErrors(["purchase_amounts.{$purchaseId}" => "Payment amount cannot exceed pending amount (" . format_currency($pendingAmount) . ")."]);
                    }
                    
                    $purchaseAmounts[$purchaseId] = $amount;
                    $totalPaymentAmount += $amount;
                }
            }
            
            if ($totalPaymentAmount <= 0) {
                return back()->withInput()->withErrors(['purchase_amounts' => 'Please enter at least one payment amount.']);
            }
        } else {
            return back()->withInput()->withErrors(['total_amount' => 'Please enter either total amount or individual purchase amounts.']);
        }

        $branchId = $request->branch_id ?: null;

        $moneySource = MoneySource::forPayments()
            ->where('company_id', $user->company_id)
            ->where('id', $request->money_source_id)
            ->where('active', true)
            ->first();

        if (! $moneySource) {
            return back()->withInput()->withErrors([
                'money_source_id' => 'Invalid or inactive payment source.',
            ]);
        }

        if ($branchId) {
            $attached = $moneySource->branches()->where('branches.id', $branchId)->exists();
            if (! $attached && $moneySource->branches()->exists()) {
                return back()->withInput()->withErrors([
                    'money_source_id' => 'This payment source is not available for the selected branch.',
                ]);
            }
        }

        $paymentMethod = match ($moneySource->type) {
            'CASH' => 'cash',
            'BANK' => 'card',
            'APP' => 'online',
            default => 'cash',
        };

        DB::beginTransaction();
        try {
            $payment = $this->createSupplierPaymentWithUniqueNumber([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'supplier_id' => $request->supplier_id,
                'account_id' => $request->account_id,
                'money_source_id' => $moneySource->id,
                'created_by' => $user->id,
                'payment_date' => $request->payment_date,
                'total_amount' => $totalPaymentAmount,
                'payment_method' => $paymentMethod,
                'kind' => SupplierPayment::KIND_PAYMENT,
                'notes' => $request->notes,
            ], $branchId);

            // Update purchases and link to payment
            foreach ($purchaseAmounts as $purchaseId => $amount) {
                $purchase = Purchase::findOrFail($purchaseId);
                
                // Update paid amount
                $currentPaid = $purchase->paid_amount ?? 0;
                $purchase->paid_amount = $currentPaid + $amount;
                
                // Update payment status
                if ($purchase->paid_amount >= $purchase->total_amount) {
                    $purchase->payment_status = 'paid';
                    $purchase->paid_amount = $purchase->total_amount; // Ensure it doesn't exceed
                } else {
                    $purchase->payment_status = 'partial';
                }
                
                $purchase->save();
                
                // Link purchase to payment
                $payment->purchases()->attach($purchaseId, ['amount' => $amount]);
            }

            // Create transaction
            Transaction::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'account_id' => $request->account_id,
                'amount' => $totalPaymentAmount,
                'type' => 'out',
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource->id,
                'reference_type' => 'purchase',
                'date' => $request->payment_date,
                'ref_id' => $payment->id,
                'created_by' => $user->id,
                'shift_id' => \App\Support\CurrentShift::id($branchId, $user),
                'notes' => "Supplier Payment #{$payment->payment_number}",
            ]);

            // Update supplier balance
            $supplier = Supplier::findOrFail($request->supplier_id);
            $supplier->balance = round((float) ($supplier->balance ?? 0) - $totalPaymentAmount, 2);
            $supplier->save();

            DB::commit();

            return redirect()
                ->route('supplier-payments.index')
                ->with('success', 'Supplier payment created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create supplier payment: ' . $e->getMessage()]);
        }
    }

    public function createAdvance(Request $request)
    {
        $this->authorizeModule('supplier-payments.store');

        $user = Auth::user();

        $suppliers = Supplier::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $accounts = Account::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $branches = \App\Models\Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $selectedSupplierId = old('supplier_id', $request->get('supplier_id'));
        $selectedBranchId = old('branch_id', $request->get('branch_id', $user->branch_id));
        $moneySources = $this->moneySourcesForBranch((int) $user->company_id, $selectedBranchId ? (int) $selectedBranchId : null);

        return view('supplier-payments.advance.create', compact(
            'suppliers',
            'accounts',
            'branches',
            'moneySources',
            'selectedSupplierId',
            'selectedBranchId'
        ));
    }

    public function storeAdvance(Request $request)
    {
        $this->authorizeModule('supplier-payments.store');

        $user = Auth::user();

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'account_id' => ['required', 'exists:accounts,id'],
            'money_source_id' => ['required', 'exists:money_sources,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        if ($supplier->company_id !== $user->company_id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        try {
            $payment = $this->supplierPaymentService->payAdvance(
                $supplier,
                (float) $validated['amount'],
                (int) $validated['account_id'],
                (int) $validated['money_source_id'],
                $user,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                $validated['payment_date'],
                $validated['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('supplier-payments.show', $payment)
            ->with('success', 'Supplier advance recorded successfully.');
    }

    /**
     * Display the specified supplier payment.
     */
    public function show(SupplierPayment $supplierPayment)
    {
        $supplierPayment->load(['supplier', 'branch', 'account', 'moneySource', 'creator', 'purchases']);
        
        return view('supplier-payments.show', compact('supplierPayment'));
    }

    public function destroy(SupplierPayment $supplierPayment)
    {
        $this->authorizeModule('supplier-payments.destroy');

        $user = Auth::user();
        if ($supplierPayment->company_id !== $user->company_id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        try {
            $this->supplierPaymentService->deletePayment($supplierPayment);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('supplier-payments.index')
            ->with('success', 'Supplier payment deleted and balances restored.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSupplierPaymentWithUniqueNumber(array $attributes, ?int $branchId): SupplierPayment
    {
        $lastDuplicate = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attributes['payment_number'] = SupplierPayment::allocatePaymentNumber($branchId);

            try {
                return SupplierPayment::create($attributes);
            } catch (\Illuminate\Database\QueryException $e) {
                if (! SupplierPayment::isDuplicateKeyException($e)) {
                    throw $e;
                }
                $lastDuplicate = $e;
            }
        }

        throw $lastDuplicate ?? new \RuntimeException('Unable to allocate a unique supplier payment number.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MoneySource>
     */
    private function moneySourcesForBranch(int $companyId, ?int $branchId)
    {
        $query = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('active', true);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId))
                    ->orWhereDoesntHave('branches');
            });
        }

        return $query->orderBy('type')->orderBy('name')->get();
    }

    private function authorizeModule(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }
}
