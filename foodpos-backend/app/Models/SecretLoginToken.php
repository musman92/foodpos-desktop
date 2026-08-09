<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecretLoginToken extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'token',
        'company_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public static function generateForCompany(Company $company, int $ttlMinutes = 15): self
    {
        return self::create([
            'token' => Str::random(64),
            'company_id' => $company->id,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
