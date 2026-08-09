<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variant extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'options',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    /**
     * Get the company that owns this variant.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all menu items that use this variant.
     */
    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_variant')
            ->withPivot('price', 'is_default')
            ->withTimestamps();
    }

    /**
     * Label for dropdowns and lists: "V01 — Size".
     */
    public function displayLabel(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }

        return $this->name;
    }

    /**
     * Generate the next auto-increment style code (V01, V02, …) for a company.
     */
    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^V(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'V'.($next < 100
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

    /**
     * Generate the next auto-increment option code (O01, O02, …) within a variant.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    public static function generateNextOptionCode(array $options): string
    {
        $max = 0;
        foreach ($options as $option) {
            $code = trim((string) ($option['code'] ?? ''));
            if (preg_match('/^O(\d+)$/i', $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'O'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    /**
     * Normalize variant options and auto-assign codes when blank.
     *
     * @param  array<int, array<string, mixed>>|null  $options
     * @return array<int, array{name: string, code: string, sort_order: int, price: ?float}>|null
     */
    public static function resolveOptions(?array $options): ?array
    {
        if (empty($options)) {
            return null;
        }

        $resolved = [];
        foreach ($options as $option) {
            if (empty($option['name'])) {
                continue;
            }

            $code = trim((string) ($option['code'] ?? ''));
            if ($code === '') {
                $code = static::generateNextOptionCode($resolved);
            }

            $price = null;
            if (isset($option['price']) && $option['price'] !== '' && $option['price'] !== null) {
                $price = round(max(0, (float) $option['price']), 2);
            }

            $resolved[] = [
                'name' => (string) $option['name'],
                'code' => $code,
                'sort_order' => isset($option['sort_order']) ? (int) $option['sort_order'] : 0,
                'price' => $price,
            ];
        }

        return $resolved !== [] ? $resolved : null;
    }

    /**
     * Default selling price for an option from variant master data.
     */
    public function defaultPriceForOption(?string $optionName): ?float
    {
        if ($optionName === null || $optionName === '') {
            return null;
        }

        foreach ($this->options ?? [] as $option) {
            if (! is_array($option) || ($option['name'] ?? '') !== $optionName) {
                continue;
            }

            if (! array_key_exists('price', $option) || $option['price'] === null || $option['price'] === '') {
                return null;
            }

            return round(max(0, (float) $option['price']), 2);
        }

        return null;
    }
}
