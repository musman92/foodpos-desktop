<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PlatformMedia extends Model
{
    use SoftDeletes;

    protected $table = 'platform_media';

    protected $fillable = [
        'title',
        'file_path',
        'category',
        'alt_text',
        'sort_order',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public static function pathPrefix(): string
    {
        return 'platform-media/';
    }

    public static function isPlatformMediaPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return str_starts_with(ltrim($path, '/'), self::pathPrefix());
    }
}
