<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountsOutstandingReport
{
    /**
     * @return array{
     *     rows: Collection<int, array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int,
     *     as_of: string
     * }
     */
    public static function receivables(User $user, ?int $branchId): array
    {
        $companyId = self::resolveCompanyId($user, $branchId);

        $query = Customer::withoutTenantScope()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('balance', '>', 0)
            ->orderByDesc('balance');

        if (! $user->isSuperAdmin()) {
            $query->where('is_active', true);
        }

        $rows = $query->get(['id', 'name', 'phone', 'email', 'balance'])
            ->map(fn (Customer $customer) => self::row(
                $customer->id,
                $customer->name,
                self::contactLabel($customer->phone, $customer->email),
                (float) $customer->balance
            ))
            ->values();

        return self::finalize($rows);
    }

    /**
     * @return array{
     *     rows: Collection<int, array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int,
     *     as_of: string
     * }
     */
    public static function payables(User $user, ?int $branchId): array
    {
        $companyId = self::resolveCompanyId($user, $branchId);

        $query = Supplier::withoutTenantScope()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('balance', '>', 0)
            ->orderByDesc('balance');

        if (! $user->isSuperAdmin()) {
            $query->where('status', 'active');
        }

        $rows = $query->get(['id', 'name', 'phone', 'email', 'contact_person', 'balance'])
            ->map(fn (Supplier $supplier) => self::row(
                $supplier->id,
                $supplier->name,
                self::contactLabel($supplier->phone, $supplier->email, $supplier->contact_person),
                (float) $supplier->balance
            ))
            ->values();

        return self::finalize($rows);
    }

    /**
     * @return array{
     *     rows: Collection<int, array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int,
     *     as_of: string
     * }
     */
    public static function customerCredits(User $user, ?int $branchId): array
    {
        $companyId = self::resolveCompanyId($user, $branchId);

        $query = Customer::withoutTenantScope()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('balance', '<', 0)
            ->orderBy('balance');

        if (! $user->isSuperAdmin()) {
            $query->where('is_active', true);
        }

        $rows = $query->get(['id', 'name', 'phone', 'email', 'balance'])
            ->map(fn (Customer $customer) => self::row(
                $customer->id,
                $customer->name,
                self::contactLabel($customer->phone, $customer->email),
                abs((float) $customer->balance)
            ))
            ->values();

        return self::finalize($rows);
    }

    /**
     * @return array{
     *     rows: Collection<int, array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int,
     *     as_of: string
     * }
     */
    public static function supplierPrepayments(User $user, ?int $branchId): array
    {
        $companyId = self::resolveCompanyId($user, $branchId);

        $query = Supplier::withoutTenantScope()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('balance', '<', 0)
            ->orderBy('balance');

        if (! $user->isSuperAdmin()) {
            $query->where('status', 'active');
        }

        $rows = $query->get(['id', 'name', 'phone', 'email', 'contact_person', 'balance'])
            ->map(fn (Supplier $supplier) => self::row(
                $supplier->id,
                $supplier->name,
                self::contactLabel($supplier->phone, $supplier->email, $supplier->contact_person),
                abs((float) $supplier->balance)
            ))
            ->values();

        return self::finalize($rows);
    }

    /**
     * @param  Collection<int, array{id: int, name: string, contact: ?string, balance: float}>  $rows
     * @return array{
     *     rows: Collection<int, array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int,
     *     as_of: string
     * }
     */
    protected static function finalize(Collection $rows): array
    {
        $total = round($rows->sum('balance'), 2);

        return [
            'rows' => $rows,
            'total' => $total,
            'party_count' => $rows->count(),
            'as_of' => now()->format('Y-m-d'),
        ];
    }

    /**
     * @return array{id: int, name: string, contact: ?string, balance: float}
     */
    protected static function row(int $id, string $name, ?string $contact, float $balance): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'contact' => $contact,
            'balance' => round($balance, 2),
        ];
    }

    protected static function contactLabel(?string $phone, ?string $email, ?string $contactPerson = null): ?string
    {
        $parts = array_filter([
            $contactPerson,
            $phone,
            $email,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    protected static function resolveCompanyId(User $user, ?int $branchId): ?int
    {
        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);

            return $branch?->company_id ? (int) $branch->company_id : null;
        }

        return $user->company_id ? (int) $user->company_id : null;
    }
}
