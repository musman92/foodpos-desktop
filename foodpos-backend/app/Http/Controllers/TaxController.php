<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxRequest;
use App\Http\Requests\UpdateTaxRequest;
use App\Models\Tax;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxController extends Controller
{
    /**
     * Display a listing of taxes.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $taxes = Tax::with('company')
            ->orderBy('name')
            ->paginate($perPage);

        return view('taxes.index', compact('taxes', 'perPage'));
    }

    /**
     * Show the form for creating a new tax.
     */
    public function create()
    {
        return view('taxes.create');
    }

    /**
     * Store a newly created tax.
     */
    public function store(StoreTaxRequest $request)
    {
        $user = Auth::user();

        $tax = Tax::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'percentage' => $request->percentage,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('taxes.index')
            ->with('success', "Tax '{$tax->name}' created successfully.");
    }

    /**
     * Display the specified tax.
     */
    public function show(Tax $tax)
    {
        $tax->load('company');

        return view('taxes.show', compact('tax'));
    }

    /**
     * Show the form for editing the specified tax.
     */
    public function edit(Tax $tax)
    {
        return view('taxes.edit', compact('tax'));
    }

    /**
     * Update the specified tax.
     */
    public function update(UpdateTaxRequest $request, Tax $tax)
    {
        $tax->update([
            'name' => $request->name,
            'percentage' => $request->percentage,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('taxes.index')
            ->with('success', "Tax '{$tax->name}' updated successfully.");
    }

    /**
     * Remove the specified tax.
     */
    public function destroy(Tax $tax)
    {
        $name = $tax->name;
        $tax->delete();

        return redirect()
            ->route('taxes.index')
            ->with('success', "Tax '{$name}' deleted successfully.");
    }
}
