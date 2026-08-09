<?php

namespace App\Services;

use App\Models\Company;
use App\Support\CompanyAddons;
use Illuminate\Support\Facades\Auth;

class CompanyAddonService
{
    public function enabled(?Company $company, string $addon): bool
    {
        if (! $company) {
            return false;
        }

        $addons = ($company->settings ?? [])['addons'] ?? [];

        return (bool) ($addons[$addon] ?? false);
    }

    public function kitchenTrackingEnabled(?Company $company = null): bool
    {
        if (! $company) {
            $user = Auth::user();
            $company = $user?->company;
        }

        return $this->enabled($company, CompanyAddons::KITCHEN_TRACKING);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $inputAddons
     * @return array<string, mixed>
     */
    public function mergeAddonsIntoSettings(array $settings, array $inputAddons): array
    {
        $merged = [];
        foreach (CompanyAddons::keys() as $key) {
            $merged[$key] = $this->normalizeAddonInput($inputAddons[$key] ?? false);
        }

        $settings['addons'] = $merged;

        return $settings;
    }

    private function normalizeAddonInput(mixed $value): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, bool>
     */
    public function addonsForCompany(?Company $company): array
    {
        $stored = ($company?->settings ?? [])['addons'] ?? [];
        $result = [];
        foreach (CompanyAddons::keys() as $key) {
            $result[$key] = (bool) ($stored[$key] ?? false);
        }

        return $result;
    }
}
