<?php

namespace App\Support;

use App\Models\Company;
use App\Services\CompanyConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ListingPerPage
{
    public const DEFAULT = 15;

    /** @var list<int> */
    public const OPTIONS = [10, 15, 25, 50];

    public static function normalize(mixed $value): int
    {
        $size = (int) $value;

        return in_array($size, self::OPTIONS, true) ? $size : self::DEFAULT;
    }

    public static function forCompany(?Company $company = null): int
    {
        if (! $company) {
            $user = Auth::user();
            $company = $user?->company;
        }

        if (! $company) {
            return self::DEFAULT;
        }

        $settings = $company->settings ?? [];

        return self::normalize($settings['listing_per_page'] ?? self::DEFAULT);
    }

    public static function fromRequest(Request $request, ?Company $company = null): int
    {
        if ($request->has('per_page') && $request->input('per_page') !== '') {
            return self::normalize($request->input('per_page'));
        }

        return self::forCompany($company);
    }
}
