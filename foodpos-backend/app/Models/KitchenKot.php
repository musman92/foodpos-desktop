<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenKot extends Model
{
    use HasTenantAndBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_id',
        'printed_by',
        'kot_number',
        'token_number',
        'type',
        'lines',
        'is_reprint',
        'printed_at',
    ];

    protected $casts = [
        'lines' => 'array',
        'is_reprint' => 'boolean',
        'printed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'full' => 'NEW',
            'add' => 'UPDATED',
            'void' => 'VOID',
            default => strtoupper((string) $this->type),
        };
    }
}
