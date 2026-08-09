<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected static function boot()
    {
        parent::boot();

        static::bootTenantScope();

        static::creating(function ($supplier) {
            if ($supplier->company_id && trim((string) ($supplier->code ?? '')) === '') {
                $supplier->code = static::resolveCode($supplier->company_id, null);
            }
        });
    }

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'whatsapp',
        'address',
        'tax_id',
        'status',
        'balance',
        'notes',
    ];

    /**
     * Get the company that owns this supplier.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all purchase orders from this supplier.
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get all purchases from this supplier.
     */
    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Get all supplier payments for this supplier.
     */
    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public static function normalizeName(?string $name): string
    {
        return trim((string) $name);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone === '' ? null : $phone;
    }

    public static function phoneDigits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) static::normalizePhone($phone)) ?? '';
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public static function nameIsTaken(int $companyId, ?string $name, ?int $ignoreId = null): bool
    {
        $normalized = strtolower(static::normalizeName($name));
        if ($normalized === '') {
            return false;
        }

        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get(['id', 'name'])
            ->contains(fn (self $supplier) => strtolower(static::normalizeName($supplier->name)) === $normalized);
    }

    public static function phoneIsTaken(int $companyId, ?string $phone, ?int $ignoreId = null): bool
    {
        $digits = static::phoneDigits($phone);
        if ($digits === '') {
            return false;
        }

        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->contains(fn (self $supplier) => static::phoneDigits($supplier->phone) === $digits);
    }

    public static function emailIsTaken(int $companyId, ?string $email, ?int $ignoreId = null): bool
    {
        $normalized = static::normalizeEmail($email);
        if ($normalized === null) {
            return false;
        }

        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereNotNull('email')
            ->get(['id', 'email'])
            ->contains(fn (self $supplier) => static::normalizeEmail($supplier->email) === $normalized);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = static::normalizeName($value);
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = static::normalizePhone($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = static::normalizeEmail($value);
    }

    /**
     * Label for dropdowns and lists: "SU01 — ABC Supplies".
     */
    public function displayLabel(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }

        return $this->name;
    }

    /**
     * Generate the next auto-increment style code (SU01, SU02, …) for a company.
     */
    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^SU(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'SU'.($next < 100
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
}

