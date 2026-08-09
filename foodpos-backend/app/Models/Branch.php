<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'timezone',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the company that owns this branch.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all users for this branch.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all tables for this branch.
     */
    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    /**
     * Floors / levels for this branch.
     */
    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    /**
     * Get all orders for this branch.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all money sources for this branch.
     */
    public function moneySources()
    {
        return $this->belongsToMany(MoneySource::class, 'branch_money_sources')
            ->withTimestamps();
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }

    public function desktopKeys()
    {
        return $this->hasMany(BranchDesktopKey::class);
    }
}
