<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyConfigService;
use App\Services\CompanyReceiptLogoService;
use App\Support\ListingPerPage;
use App\Support\PosLayout;
use App\Support\ReceiptSections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanySettingsController extends Controller
{
    /**
     * Show the company settings form.
     */
    public function index()
    {
        $user = Auth::user();

        // Super admins can manage any company
        if ($user->isSuperAdmin()) {
            // For super admin, allow selecting company from query param or use first
            $companyId = request()->query('company_id');
            if ($companyId) {
                $company = Company::find($companyId);
                if (!$company) {
                    return redirect()->route('dashboard')
                        ->with('error', 'Company not found.');
                }
            } else {
                $company = Company::first();
                if (!$company) {
                    return redirect()->route('dashboard')
                        ->with('error', 'No companies found. Please create a company first.');
                }
            }
        } else {
            $company = $user->company;
            if (!$company) {
                return redirect()->route('dashboard')
                    ->with('error', 'You are not associated with a company.');
            }
        }

        // Get the active section from query parameter, default to 'general'
        $activeSection = request()->query('section', 'general');
        
        // Define available sections
        $sections = [
            'general' => [
                'title' => 'General',
                'icon' => 'fa-building',
                'description' => 'Company information, branding, and basic settings',
            ],
            'preferences' => [
                'title' => 'Preferences',
                'icon' => 'fa-cog',
                'description' => 'Currency, timezone, display, HR, and list pagination preferences',
            ],
            'pos' => [
                'title' => 'Point of Sale',
                'icon' => 'fa-cash-register',
                'description' => 'POS layout, checkout behaviour, and category bar',
            ],
            'receipt' => [
                'title' => 'Invoice / Receipt',
                'icon' => 'fa-receipt',
                'description' => 'Printed customer invoice layout, paper size, and content options',
            ],
        ];

        // Validate section exists
        if (!isset($sections[$activeSection])) {
            $activeSection = 'general';
        }

        return view('company-settings.index', compact('company', 'activeSection', 'sections'));
    }

    /**
     * Update general company settings (company info + branding).
     */
    public function updateGeneral(Request $request, Company $company)
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403, 'You do not have permission to update this company.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('companies')->ignore($company->id)],
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png,jpg,jpeg|max:512',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoService = app(CompanyReceiptLogoService::class);

            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }
            $logoService->deletePrintLogo($company->logo_print);

            $logoPath = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo'] = $logoPath;
            $validated['logo_print'] = $logoService->generateFromUpload($request->file('logo'), $logoPath);
        } else {
            unset($validated['logo']);
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            // Delete old favicon if exists
            if ($company->favicon && Storage::disk('public')->exists($company->favicon)) {
                Storage::disk('public')->delete($company->favicon);
            }
            
            $faviconPath = $request->file('favicon')->store('companies/favicons', 'public');
            $validated['favicon'] = $faviconPath;
        } else {
            unset($validated['favicon']);
        }

        $company->update($validated);

        CompanyConfigService::warmSessionCaches($company->fresh());

        return redirect()->route('company-settings.index', ['section' => 'general'])
            ->with('success', 'General settings updated successfully.');
    }

    /**
     * Update preferences (currency, timezone, display settings).
     */
    public function updatePreferences(Request $request, Company $company)
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403, 'You do not have permission to update this company.');
        }

        $validated = $request->validate([
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:255',
            'currency_position' => 'required|in:left,right',
            'decimal_points' => 'required|integer|min:0|max:4',
            'time_format' => 'required|in:12,24',
            'date_format' => 'required|in:Y-m-d,d-m-Y,m-d-Y,d/m/Y,m/d/Y,Y/m/d',
            'week_starts_on' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'listing_per_page' => ['required', 'integer', Rule::in(ListingPerPage::OPTIONS)],
            'strict_direct_pay_rate' => 'nullable|boolean',
            'activity_logging_enabled' => 'nullable|boolean',
        ]);

        // Store configuration in settings JSON
        $settings = $company->settings ?? [];
        $settings['week_starts_on'] = $validated['week_starts_on'];
        $settings['currency_position'] = $validated['currency_position'];
        $settings['decimal_points'] = $validated['decimal_points'];
        $settings['time_format'] = $validated['time_format'];
        $settings['date_format'] = $validated['date_format'];
        $settings['listing_per_page'] = ListingPerPage::normalize($validated['listing_per_page']);
        $settings['strict_direct_pay_rate'] = $request->boolean('strict_direct_pay_rate');
        $settings['activity_logging_enabled'] = (bool) ($validated['activity_logging_enabled'] ?? false);

        // Update currency and timezone directly on company
        $company->update([
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'settings' => $settings,
        ]);

        CompanyConfigService::warmSessionCaches($company->fresh());
        \App\Services\ActivityLogger::clearCache();

        return redirect()->route('company-settings.index', ['section' => 'preferences'])
            ->with('success', 'Preferences updated successfully.');
    }

    /**
     * Update point-of-sale settings (layout, checkout).
     */
    public function updatePos(Request $request, Company $company)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403, 'You do not have permission to update this company.');
        }

        $validated = $request->validate([
            'allow_pos_credit_sales' => 'nullable|boolean',
            'direct_pos_print' => 'nullable|boolean',
            'show_pos_auto_bill_toggle' => 'nullable|boolean',
            'pos_layout' => 'nullable|string|in:'.implode(',', array_keys(PosLayout::layoutPresets())),
            'pos_product_density' => 'nullable|string|in:'.implode(',', array_keys(PosLayout::productDensities())),
            'pos_order_context_style' => 'nullable|string|in:'.implode(',', array_keys(PosLayout::orderContextStyles())),
            'pos_category_size' => 'nullable|string|in:'.implode(',', array_keys(PosLayout::categorySizes())),
            'pos_category_layout' => 'nullable|string|in:'.implode(',', array_keys(PosLayout::categoryLayouts())),
        ]);

        $settings = $company->settings ?? [];
        $settings['allow_pos_credit_sales'] = $request->boolean('allow_pos_credit_sales');
        $settings['direct_pos_print'] = $request->boolean('direct_pos_print');
        $settings['show_pos_auto_bill_toggle'] = $request->boolean('show_pos_auto_bill_toggle');
        $settings['pos_layout'] = PosLayout::normalizeLayout($validated['pos_layout'] ?? null);
        $settings['pos_product_density'] = PosLayout::normalizeProductDensity($validated['pos_product_density'] ?? null);
        $settings['pos_order_context_style'] = PosLayout::normalizeOrderContextStyle($validated['pos_order_context_style'] ?? null);
        $settings['pos_category_size'] = PosLayout::normalizeCategorySize($validated['pos_category_size'] ?? null);
        $settings['pos_category_layout'] = PosLayout::normalizeCategoryLayout($validated['pos_category_layout'] ?? null);

        $company->update(['settings' => $settings]);

        CompanyConfigService::warmSessionCaches($company->fresh());

        return redirect()->route('company-settings.index', ['section' => 'pos'])
            ->with('success', 'Point of Sale settings updated successfully.');
    }

    /**
     * Update printed invoice / receipt settings.
     */
    public function updateReceipt(Request $request, Company $company)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403, 'You do not have permission to update this company.');
        }

        $validated = $request->validate([
            'receipt_font_size' => 'nullable|integer|min:10|max:20',
            'receipt_paper_width_mm' => 'nullable|integer|in:58,80',
            'receipt_sections' => 'nullable|array',
            'receipt_sections.*' => 'nullable|boolean',
        ]);

        $settings = $company->settings ?? [];
        $settings['receipt_font_size'] = CompanyConfigService::normalizeReceiptFontSize($validated['receipt_font_size'] ?? null);
        $settings['receipt_paper_width_mm'] = CompanyConfigService::normalizeReceiptPaperWidth($validated['receipt_paper_width_mm'] ?? null);
        $settings['receipt_sections'] = ReceiptSections::normalize($request->input('receipt_sections'));

        $company->update(['settings' => $settings]);

        CompanyConfigService::warmSessionCaches($company->fresh());

        return redirect()->route('company-settings.index', ['section' => 'receipt'])
            ->with('success', 'Invoice / receipt settings updated successfully.');
    }
}
