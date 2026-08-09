<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes, HasTenantAndBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'created_by',
        'category',
        'description',
        'amount',
        'expense_date',
        'receipt',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Get the company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who created this expense.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

