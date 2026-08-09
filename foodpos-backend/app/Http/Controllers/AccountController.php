<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $accounts = Account::with('company')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($perPage);

        return view('accounts.index', compact('accounts', 'perPage'));
    }

    /**
     * Show the form for creating a new account.
     */
    public function create()
    {
        return view('accounts.create');
    }

    /**
     * Store a newly created account.
     */
    public function store(StoreAccountRequest $request)
    {
        $user = Auth::user();

        $account = Account::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'type' => $request->type,
            'is_active' => $request->has('is_active') ? true : false,
            'is_deletable' => true, // User-created accounts are deletable
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', "Account '{$account->name}' created successfully.");
    }

    /**
     * Display the specified account.
     */
    public function show(Account $account)
    {
        $account->load('company', 'transactions');

        return view('accounts.show', compact('account'));
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit(Account $account)
    {
        if (! $account->canBeEdited()) {
            return redirect()
                ->route('accounts.index')
                ->with('error', "Account '{$account->name}' is a default system account and cannot be edited.");
        }

        return view('accounts.edit', compact('account'));
    }

    /**
     * Update the specified account.
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        if (! $account->canBeEdited()) {
            return redirect()
                ->route('accounts.index')
                ->with('error', "Account '{$account->name}' is a default system account and cannot be edited.");
        }

        $account->update([
            'name' => $request->name,
            'type' => $request->type,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', "Account '{$account->name}' updated successfully.");
    }

    /**
     * Remove the specified account.
     */
    public function destroy(Account $account)
    {
        if (! $account->canBeDeleted()) {
            return redirect()
                ->route('accounts.index')
                ->with('error', "Account '{$account->name}' cannot be deleted as it is a default system account.");
        }

        $name = $account->name;
        $account->delete();

        return redirect()
            ->route('accounts.index')
            ->with('success', "Account '{$name}' deleted successfully.");
    }
}
