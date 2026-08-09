<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use App\Traits\StampsBusinessDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneySourceFundMovement extends Model
{
    use HasTenantAndBranch, StampsBusinessDate;

    public const TYPE_OWNER_WITHDRAWAL = 'owner_withdrawal';

    protected $fillable = [
        'company_id',
        'branch_id',
        'from_money_source_id',
        'to_money_source_id',
        'movement_type',
        'amount',
        'movement_date',
        'notes',
        'created_by',
        'shift_id',
        'business_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'movement_date' => 'date',
        'business_date' => 'date',
    ];

    public function fromMoneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class, 'from_money_source_id');
    }

    public function toMoneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class, 'to_money_source_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
