<?php

namespace App\Http\Controllers;

use App\Models\PlatformInvoice;
use App\Services\PlatformInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformInvoicePaymentController extends Controller
{
    public function __construct(private PlatformInvoiceService $invoices) {}

    public function store(Request $request, PlatformInvoice $platformInvoice): RedirectResponse
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys(config('platform_billing.payment_methods', [])))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->invoices->recordPayment($platformInvoice, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }
}
