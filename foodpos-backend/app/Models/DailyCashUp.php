<?php

namespace App\Models;

use App\Traits\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyCashUp extends Model
{
    use HasFactory, BranchScope;

    protected $table = 'daily_cash_ups';

    protected $fillable = [
        'branch_id',
        'user_id',
        'cash_up_date',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'card_sales',
        'digital_wallet_sales',
        'total_sales',
        'refunds',
        'notes',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'cash_up_date' => 'date',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'digital_wallet_sales' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'refunds' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who did the cash up.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

