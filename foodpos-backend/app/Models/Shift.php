<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes, HasTenantAndBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'opened_by',
        'closed_by',
        'shift_date',
        'opened_at',
        'closed_at',
        'status',
        'opening_notes',
        'closing_notes',
        'expected_cash',
        'actual_cash',
        'cash_difference',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
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
     * Get the user who opened the shift.
     */
    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Shift owner (cashier) for this session.
     */
    public function owner()
    {
        return $this->openedBy();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user who closed the shift.
     */
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get all money sources for this shift.
     */
    public function moneySources()
    {
        return $this->belongsToMany(MoneySource::class, 'shift_money_sources')
            ->withPivot('opening_balance', 'closing_balance', 'expected_balance', 'difference', 'notes')
            ->withTimestamps();
    }

    /**
     * Check if shift is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if shift is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Get active shift for a branch (legacy — first match only).
     *
     * @deprecated Use getActiveShiftForUser() for per-user shifts.
     */
    public static function getActiveShift(int $branchId): ?self
    {
        return static::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Active shift for a specific user at a branch.
     */
    public static function getActiveShiftForUser(int $branchId, int $userId): ?self
    {
        return static::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->where('opened_by', $userId)
            ->where('status', 'active')
            ->first();
    }

    public function isOwnedBy(int $userId): bool
    {
        return (int) $this->opened_by === $userId;
    }
}
