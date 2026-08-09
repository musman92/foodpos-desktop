<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use TenantScope;

    protected $fillable = [
        'company_id',
        'branch_id',
        'printer_id',
        'document_type',
        'reference_type',
        'reference_id',
        'print_url',
        'access_token',
        'device_name',
        'status',
        'error_message',
        'printed_at',
        'acked_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'acked_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public static function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
