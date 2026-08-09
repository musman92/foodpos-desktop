<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    use TenantScope;

    public const MODE_BROWSER_POPUP = 'browser';

    public const MODE_DIRECT = 'desktop';

    protected $fillable = [
        'company_id',
        'branch_id',
        'title',
        'role',
        'printing_mode',
        'device_name',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function printingModeLabel(): string
    {
        return $this->printing_mode === self::MODE_DIRECT
            ? 'Direct print'
            : 'Browser popup print';
    }

    public function isDirectPrint(): bool
    {
        return $this->printing_mode === self::MODE_DIRECT;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function activeForBranch(int $branchId, string $role): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('branch_id', $branchId)
            ->where('role', $role)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();
    }
}
