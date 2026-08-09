<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLoyalty extends Model
{
    use HasFactory, TenantScope;

    protected $table = 'customer_loyalty';

    protected $fillable = [
        'company_id',
        'customer_phone',
        'customer_name',
        'customer_email',
        'total_points',
        'redeemed_points',
        'tier',
        'lifetime_spent',
        'total_orders',
        'last_order_at',
    ];

    protected $casts = [
        'total_points' => 'decimal:2',
        'redeemed_points' => 'decimal:2',
        'lifetime_spent' => 'decimal:2',
        'last_order_at' => 'datetime',
    ];

    /**
     * Get the company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get available points (total - redeemed).
     */
    public function getAvailablePointsAttribute(): float
    {
        return $this->total_points - $this->redeemed_points;
    }
}

