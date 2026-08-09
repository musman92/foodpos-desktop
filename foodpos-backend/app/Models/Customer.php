<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class Customer extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    public const DELETED_PHONE_PREFIX = 'del-';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'notes',
        'balance',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'balance' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::bootTenantScope();

        // Prevent deletion of default customers
        static::deleting(function ($customer) {
            if ($customer->is_default) {
                throw new \Exception('Default customers cannot be deleted.');
            }
        });

        static::deleted(function (self $customer) {
            if (! $customer->trashed() || $customer->phone === null) {
                return;
            }

            $archivedPhone = static::archivedDeletePhone($customer->phone, (int) $customer->id);
            if ($archivedPhone === $customer->phone) {
                return;
            }

            static::withoutGlobalScopes()
                ->whereKey($customer->id)
                ->update(['phone' => $archivedPhone]);
        });

        static::creating(function ($customer) {
            if ($customer->company_id && trim((string) ($customer->code ?? '')) === '') {
                $customer->code = static::resolveCode($customer->company_id, null);
            }
        });
    }

    /**
     * Get the company that owns this customer.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all addresses for this customer.
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get the default address for this customer.
     */
    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    /**
     * Get all orders for this customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Payments received against this customer's balance.
     */
    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Label for dropdowns and lists: "CU01 — John Doe".
     */
    public function displayLabel(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }

        return $this->name;
    }

    /**
     * Generate the next auto-increment style code (CU01, CU02, …) for a company.
     */
    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^CU(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'CU'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    /**
     * Resolve the code to store: use user input or auto-generate when blank.
     */
    public static function resolveCode(?int $companyId, ?string $requestedCode): string
    {
        $code = trim((string) $requestedCode);

        return $code !== '' ? $code : static::generateNextCode($companyId);
    }

    public static function requireTenantCompanyId(?int $companyId = null): int
    {
        $resolved = $companyId ?? auth()->user()?->company_id;
        if (! $resolved) {
            abort(403, 'Tenant company context is required.');
        }

        return (int) $resolved;
    }

    /**
     * Unique rule: phone is unique within a tenant (company), not globally.
     */
    public static function tenantUniquePhoneRule(int $companyId, ?int $ignoreCustomerId = null): Unique
    {
        $rule = Rule::unique('customers', 'phone')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        if ($ignoreCustomerId) {
            $rule->ignore($ignoreCustomerId);
        }

        return $rule;
    }

    /**
     * @return list<mixed>
     */
    public static function tenantPhoneValidationRules(int $companyId, ?int $ignoreCustomerId = null): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $ignoreCustomerId): void {
                $conflict = static::findPhoneConflict($companyId, is_string($value) ? $value : null, $ignoreCustomerId);
                if ($conflict) {
                    $fail(static::phoneDuplicateMessage($conflict));
                }
            },
        ];
    }

    /**
     * Unique rule: customer code is unique within a tenant (company), not globally.
     */
    public static function tenantUniqueCodeRule(int $companyId, ?int $ignoreCustomerId = null): Unique
    {
        $rule = Rule::unique('customers', 'code')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        if ($ignoreCustomerId) {
            $rule->ignore($ignoreCustomerId);
        }

        return $rule;
    }

    /**
     * @return list<mixed>
     */
    public static function tenantCodeValidationRules(int $companyId, ?int $ignoreCustomerId = null): array
    {
        return [
            'nullable',
            'string',
            'max:20',
            static::tenantUniqueCodeRule($companyId, $ignoreCustomerId),
        ];
    }

    /**
     * Trim phone input; empty values become null.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone === '' ? null : $phone;
    }

    public static function phoneIsArchived(?string $phone): bool
    {
        $phone = static::normalizePhone($phone);

        return $phone !== null && str_starts_with($phone, static::DELETED_PHONE_PREFIX);
    }

    public static function formatArchivedPhone(int $customerId, string $phone): string
    {
        return static::DELETED_PHONE_PREFIX.$customerId.'-'.static::normalizePhone($phone);
    }

    /**
     * Restore the original phone from an archived value (legacy or id-scoped).
     */
    public static function originalPhoneFromArchived(?string $phone): ?string
    {
        $phone = static::normalizePhone($phone);
        if ($phone === null || ! static::phoneIsArchived($phone)) {
            return $phone;
        }

        $rest = substr($phone, strlen(static::DELETED_PHONE_PREFIX));
        if ($rest === '') {
            return null;
        }

        if (preg_match('/^\d+-(.+)$/', $rest, $matches)) {
            return $matches[1] !== '' ? $matches[1] : null;
        }

        return $rest;
    }

    /**
     * Prefix phone on soft delete so the unique index frees the original number.
     */
    public static function archivedDeletePhone(?string $phone, ?int $customerId = null): ?string
    {
        $phone = static::normalizePhone($phone);
        if ($phone === null) {
            return null;
        }

        if ($customerId === null) {
            return static::phoneIsArchived($phone) ? $phone : static::DELETED_PHONE_PREFIX.$phone;
        }

        $original = static::phoneIsArchived($phone)
            ? static::originalPhoneFromArchived($phone)
            : $phone;

        if ($original === null || $original === '') {
            return null;
        }

        return static::formatArchivedPhone($customerId, $original);
    }

    /**
     * Digits-only phone for duplicate comparison.
     */
    public static function phoneDigits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) static::normalizePhone($phone)) ?? '';
    }

    /**
     * Whether another customer in the company already uses this phone (digits match).
     */
    public static function phoneIsTaken(int $companyId, ?string $phone, ?int $ignoreId = null): bool
    {
        return static::findPhoneConflict($companyId, $phone, $ignoreId) !== null;
    }

    /**
     * Find an existing customer in the same tenant with the same phone digits.
     */
    public static function findPhoneConflict(int $companyId, ?string $phone, ?int $ignoreId = null): ?self
    {
        $digits = static::phoneDigits($phone);
        if ($digits === '') {
            return null;
        }

        return static::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'deleted_at', 'name'])
            ->first(function (self $customer) use ($digits) {
                if ($customer->trashed() && static::phoneIsArchived($customer->phone)) {
                    return false;
                }

                return static::phoneDigits($customer->phone) === $digits;
            });
    }

    public static function phoneDuplicateMessage(?self $conflict): string
    {
        if ($conflict?->trashed()) {
            return 'This phone number belongs to a deleted customer. Restore that customer or use a different number.';
        }

        return 'This phone number is already assigned to another customer.';
    }

    public static function assertPhoneAvailable(int $companyId, ?string $phone, ?int $ignoreId = null): void
    {
        $conflict = static::findPhoneConflict($companyId, $phone, $ignoreId);
        if ($conflict) {
            throw new \InvalidArgumentException(static::phoneDuplicateMessage($conflict));
        }
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = static::normalizePhone($value);
    }
}
