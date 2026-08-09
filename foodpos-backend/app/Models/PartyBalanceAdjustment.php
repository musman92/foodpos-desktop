<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PartyBalanceAdjustment extends Model
{
    use TenantScope;

    public const PARTY_CUSTOMER = 'customer';

    public const PARTY_SUPPLIER = 'supplier';

    protected $fillable = [
        'company_id',
        'party_type',
        'party_id',
        'previous_balance',
        'new_balance',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function party(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'party_type', 'party_id');
    }
}
