<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MoneySource;

class PaymentMethodService
{
    public static function paymentMethodFromMoneySourceType(string $type): string
    {
        return match (strtoupper($type)) {
            'CASH' => 'cash',
            'BANK' => 'transfer',
            'APP' => 'online',
            default => 'cash',
        };
    }

    /**
     * Map payment method to money source type.
     */
    public static function getMoneySourceType(string $paymentMethod): ?string
    {
        $map = [
            'cash' => 'CASH',
            'card' => 'BANK',
            'transfer' => 'BANK',
            'online' => 'APP',
            'digital_wallet' => 'APP',
        ];

        return $map[strtolower($paymentMethod)] ?? null;
    }

    /**
     * Get money source for a payment method and branch.
     * Returns the first active money source of the matching type for the branch.
     */
    public static function getMoneySourceForPaymentMethod(string $paymentMethod, int $branchId, ?int $companyId = null): ?MoneySource
    {
        $moneySourceType = self::getMoneySourceType($paymentMethod);
        
        if (!$moneySourceType) {
            return null;
        }

        // Get branch to ensure we have company_id
        $branch = Branch::find($branchId);
        if (!$branch) {
            return null;
        }

        $companyId = $companyId ?? $branch->company_id;

        // Get money sources for this branch that match the type
        $moneySources = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('type', $moneySourceType)
            ->where('active', true)
            ->whereHas('branches', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            })
            ->orderBy('name')
            ->get();

        // Return the first one, or if none found, return any active money source of that type for the company
        if ($moneySources->isEmpty()) {
            return MoneySource::forPayments()
                ->where('company_id', $companyId)
                ->where('type', $moneySourceType)
                ->where('active', true)
                ->orderBy('name')
                ->first();
        }

        return $moneySources->first();
    }

    /**
     * Get all money sources for a payment method and branch.
     */
    public static function getMoneySourcesForPaymentMethod(string $paymentMethod, int $branchId, ?int $companyId = null): \Illuminate\Support\Collection
    {
        $moneySourceType = self::getMoneySourceType($paymentMethod);
        
        if (!$moneySourceType) {
            return collect();
        }

        // Get branch to ensure we have company_id
        $branch = Branch::find($branchId);
        if (!$branch) {
            return collect();
        }

        $companyId = $companyId ?? $branch->company_id;

        // Get money sources for this branch that match the type
        return MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('type', $moneySourceType)
            ->where('active', true)
            ->whereHas('branches', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            })
            ->orderBy('name')
            ->get();
    }
}

