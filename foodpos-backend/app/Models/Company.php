<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = [
        'logo_url',
    ];

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'logo',
        'logo_print',
        'favicon',
        'tax_id',
        'currency',
        'timezone',
        'status',
        'demo',
        'billing_currency',
        'billing_amount',
        'billing_interval',
        'billing_enabled',
        'billing_notes',
        'billing_due_date',
        'trial_ends_at',
        'billing_starts_at',
        'settings',
        'subscription_expires_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'demo' => 'boolean',
        'billing_enabled' => 'boolean',
        'billing_amount' => 'decimal:2',
        'billing_due_date' => 'date',
        'billing_starts_at' => 'date',
        'trial_ends_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Create default "Walk In" customer when a company is created
        static::created(function ($company) {
            Customer::withoutTenantScope()->create([
                'company_id' => $company->id,
                'name' => 'Walk In',
                'email' => null,
                'phone' => null,
                'is_default' => true,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Get all branches for this company.
     */
    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * All dine-in tables for this company (across branches).
     */
    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    /**
     * Get all customers for this company.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get all users for this company.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function platformInvoices()
    {
        return $this->hasMany(PlatformInvoice::class);
    }

    /**
     * Tenants that should appear in platform billing (excludes demo accounts).
     */
    public function scopeBillable($query)
    {
        return $query
            ->where('demo', false)
            ->where('billing_enabled', true);
    }

    public function isBillable(): bool
    {
        return ! $this->demo && $this->billing_enabled;
    }

    public function billingCurrency(): string
    {
        return strtoupper($this->billing_currency ?: $this->currency ?: 'USD');
    }

    public function billingIntervalLabel(): string
    {
        return \App\Support\TenantBilling::intervalLabel($this->billing_interval);
    }

    public function isOnTrial(): bool
    {
        return \App\Support\TenantBilling::isOnTrial($this);
    }

    public function outstandingBalance(): float
    {
        return \App\Support\TenantBilling::outstandingBalance($this);
    }

    public function billingStatusLabel(): string
    {
        return \App\Support\TenantBilling::billingStatusLabel($this);
    }

    /**
     * Check if company subscription is active.
     */
    public function isSubscriptionActive(): bool
    {
        return \App\Support\TenantBilling::hasActiveAccess($this);
    }

    /**
     * Public URL for the company logo (absolute, safe for print windows / JSON).
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? 0;

        return url(Storage::url($this->logo)).'?v='.$version;
    }

    /**
     * Logo URL optimized for thermal receipt printing (B&W when available).
     */
    public function getReceiptLogoUrlAttribute(): ?string
    {
        $path = $this->logo_print ?: $this->logo;
        if (! $path) {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? 0;

        return url(Storage::url($path)).'?v='.$version;
    }

    public function usesReceiptLogoFallbackFilter(): bool
    {
        return $this->logo && ! $this->logo_print;
    }
}
