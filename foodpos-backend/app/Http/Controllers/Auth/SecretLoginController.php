<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecretLoginToken;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SecretLoginController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    /**
     * Consume a secret login token and log in as the company admin.
     * Public route - no auth required.
     */
    public function login(Request $request, string $token): RedirectResponse|View
    {
        $record = SecretLoginToken::where('token', $token)->first();

        if (!$record || !$record->isValid()) {
            return redirect()->route('login')
                ->with('error', 'This login link is invalid or has expired.');
        }

        $company = $record->company;
        $user = $company->users()
            ->where('type', 'company_admin')
            ->orderBy('id')
            ->first();

        if (!$user) {
            $user = $company->users()->orderBy('id')->first();
        }

        if (!$user) {
            $record->markAsUsed();
            return redirect()->route('login')
                ->with('error', 'This company has no users.');
        }

        if ($user->status !== 'active') {
            $record->markAsUsed();
            return redirect()->route('login')
                ->with('error', 'The company admin account is not active.');
        }

        if (! $user->canLogin()) {
            $record->markAsUsed();

            return redirect()->route('login')
                ->with('error', 'The company admin account is not allowed to sign in.');
        }

        $record->markAsUsed();

        Auth::login($user, $remember = false);
        $request->session()->regenerate();

        if ($user->company_id && $user->company) {
            \App\Services\CompanyConfigService::warmSessionCaches($user->company);
        }

        if (! $user->isSuperAdmin()) {
            \App\Support\BranchContext::syncRequestContext($user);
        }

        $branchId = \App\Support\BranchContext::currentBranchId($user);
        if ($branchId && ! $user->isSuperAdmin() && ! $this->shiftService->hasActiveShift($branchId, (int) $user->id)) {
            $request->session()->put('shift_reminder', [
                'branch_id' => $branchId,
            ]);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'You are now logged in as ' . $user->name . '.');
    }
}
