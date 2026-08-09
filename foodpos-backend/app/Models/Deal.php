<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'image',
        'price',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'deal_menu_item')
            ->withPivot('quantity', 'variant_id', 'option_name', 'unit_price')
            ->withTimestamps();
    }

    /**
     * Menu items to list under a deal on invoices/receipts (qty includes deal line quantity).
     *
     * @return list<array{name: string, quantity: float}>
     */
    public function invoiceComponentLines(float $dealLineQuantity = 1.0): array
    {
        // Signed/browser print may run without auth — skip MenuItem tenant scope.
        $menuItems = $this->menuItems()->withoutGlobalScopes()->get();

        $lines = [];
        foreach ($menuItems as $menuItem) {
            $pivotQty = (float) ($menuItem->pivot->quantity ?? 1);
            $option = trim((string) ($menuItem->pivot->option_name ?? ''));
            $name = (string) $menuItem->name;
            if ($option !== '') {
                $name .= ' ('.$option.')';
            }

            $lines[] = [
                'name' => $name,
                'quantity' => round($pivotQty * max(0, $dealLineQuantity), 2),
            ];
        }

        return $lines;
    }

    /**
     * Scope: currently active (within date/time range and is_active).
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();
        $today = $now->toDateString();
        $time = $now->format('H:i:s');

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->where(function (Builder $q) use ($time) {
                $q->whereNull('start_time')->orWhereTime('start_time', '<=', $time);
            })
            ->where(function (Builder $q) use ($time) {
                $q->whereNull('end_time')->orWhereTime('end_time', '>=', $time);
            });
    }
}
