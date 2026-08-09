<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BranchDesktopKey extends Model
{
    use TenantScope;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'key_hash',
        'key_prefix',
        'is_active',
        'last_used_at',
        'connection_code',
        'last_heartbeat_at',
        'system_printers',
        'system_printers_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'system_printers_at' => 'datetime',
        'system_printers' => 'array',
    ];

    public function isOnline(): bool
    {
        return $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->gt(now()->subSeconds(90));
    }

    public static function generateConnectionCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array{model: self, plain_key: string}
     */
    public static function generateForBranch(Branch $branch, string $name): array
    {
        $plainKey = 'bdk_'.Str::random(40);

        $model = self::create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'name' => $name,
            'key_hash' => hash('sha256', $plainKey),
            'key_prefix' => substr($plainKey, 0, 12),
            'is_active' => true,
        ]);

        return ['model' => $model, 'plain_key' => $plainKey];
    }

    public static function findByPlainKey(string $plainKey): ?self
    {
        if ($plainKey === '' || ! str_starts_with($plainKey, 'bdk_')) {
            return null;
        }

        return self::withoutGlobalScope('tenant')
            ->where('key_hash', hash('sha256', $plainKey))
            ->where('is_active', true)
            ->first();
    }
}
