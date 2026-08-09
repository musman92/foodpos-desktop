<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'is_active',
        'is_deletable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deletable' => 'boolean',
    ];

    /**
     * Get the company that owns this account.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all transactions for this account.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Default/system accounts are locked: cannot be renamed, retyped, or deleted.
     */
    public function isSystemAccount(): bool
    {
        return ! $this->is_deletable;
    }

    /**
     * Check if account can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->isSystemAccount();
    }

    /**
     * Check if account can be edited (name, type, active).
     */
    public function canBeEdited(): bool
    {
        return ! $this->isSystemAccount();
    }

    /**
     * Find or create a non-deletable system account for a company.
     */
    public static function ensureSystemAccount(int $companyId, string $name, string $type): self
    {
        return static::withoutTenantScope()->firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => $name,
            ],
            [
                'type' => $type,
                'is_active' => true,
                'is_deletable' => false,
            ]
        );
    }
}
