<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CompanyReceiptBrandingService
{
    public const SESSION_KEY = 'company_receipt_branding';

    /**
     * Build branding from DB and store in session (login, settings save).
     */
    public static function warmSession(?Company $company = null): array
    {
        $payload = self::build($company);
        Session::put(self::SESSION_KEY, $payload);

        return $payload;
    }

    /**
     * Read branding from session; rebuild once if missing or company mismatch.
     */
    public static function get(?Company $company = null): array
    {
        $companyId = $company?->id ?? Auth::user()?->company_id;

        $cached = Session::get(self::SESSION_KEY);
        if (is_array($cached) && $companyId && (int) ($cached['company_id'] ?? 0) === (int) $companyId) {
            return $cached;
        }

        if (is_array($cached) && ! $companyId) {
            return $cached;
        }

        $companyModel = $company ?? Auth::user()?->company;

        return $companyModel
            ? self::warmSession($companyModel)
            : ['company_id' => null, 'company' => null, 'branches' => []];
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return array{company_id: int|null, company: array<string, mixed>|null, branches: array<int, array<string, mixed>>}
     */
    public static function build(?Company $company): array
    {
        if (! $company) {
            return ['company_id' => null, 'company' => null, 'branches' => []];
        }

        $company->refresh();

        $branches = Branch::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'address'])
            ->keyBy('id')
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
            ])
            ->all();

        return [
            'company_id' => $company->id,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo' => $company->logo,
                'logo_print' => $company->logo_print,
                'phone' => $company->phone,
                'address' => $company->address,
            ],
            'branches' => $branches,
        ];
    }

    /**
     * Apply session-cached company/branch header fields to an order for receipts.
     */
    public static function applyToOrder(Order $order): Order
    {
        $branding = self::get();

        if ($branding['company_id'] && (int) $branding['company_id'] !== (int) $order->company_id) {
            $company = Company::query()->find($order->company_id);
            $branding = $company ? self::warmSession($company) : $branding;
        }

        if (! empty($branding['company'])) {
            $company = new Company;
            $company->forceFill($branding['company']);
            $company->id = $branding['company']['id'];
            $company->exists = true;
            $order->setRelation('company', $company);
        }

        if ($order->branch_id && isset($branding['branches'][$order->branch_id])) {
            $branch = new Branch;
            $branch->forceFill($branding['branches'][$order->branch_id]);
            $branch->id = (int) $order->branch_id;
            $branch->exists = true;
            $order->setRelation('branch', $branch);
        }

        $company = $order->relationLoaded('company') ? $order->company : null;
        if (! $company && $order->company_id) {
            $company = Company::query()->find($order->company_id);
        }
        if ($company) {
            CompanyConfigService::warmSessionCaches($company);
        }

        return $order;
    }
}
