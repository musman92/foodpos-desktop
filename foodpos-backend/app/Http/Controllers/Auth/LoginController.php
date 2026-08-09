<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        
        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        $existingUser = User::where('email', $credentials['email'])->first();
        if ($existingUser && ! $existingUser->canLogin()) {
            throw ValidationException::withMessages([
                'email' => 'This account is not permitted to sign in. Contact your administrator.',
            ]);
        }

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Check if user is active
            if ($user->status !== 'active') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact administrator.',
                ]);
            }

            if (! $user->canLogin()) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'This account is not permitted to sign in. Contact your administrator.',
                ]);
            }

            // Cache company preferences + receipt branding for the session
            if ($user->company_id && $user->company) {
                \App\Services\CompanyConfigService::warmSessionCaches($user->company);
            }

            if (! $user->isSuperAdmin()) {
                \App\Support\BranchContext::syncRequestContext($user);
            }

            if (! $user->isSuperAdmin()) {
                $branchId = \App\Support\BranchContext::currentBranchId($user);
                if ($branchId && ! $this->shiftService->hasActiveShift($branchId, (int) $user->id)) {
                    $request->session()->put('shift_reminder', [
                        'branch_id' => $branchId,
                    ]);
                }
            }

            // Redirect based on user type
            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
