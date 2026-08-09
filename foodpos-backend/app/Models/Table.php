<?php

namespace App\Models;

use App\Traits\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Table extends Model
{
    use BranchScope, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'floor_id',
        'name',
        'slug',
        'code',
        'capacity',
        'status',
        'section',
        'position',
        'notes',
    ];

    protected $casts = [
        'position' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Table $table) {
            if (! $table->company_id && $table->branch_id) {
                $table->company_id = Branch::withoutGlobalScope('tenant')
                    ->where('id', $table->branch_id)
                    ->value('company_id');
            }
        });
    }

    /**
     * Unique slug per company (URL-safe).
     */
    public static function generateUniqueSlug(int $companyId, string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'table';
        }
        $slug = $base;
        for ($i = 1; ; $i++) {
            $q = static::withoutGlobalScope('branch')
                ->where('company_id', $companyId)
                ->where('slug', $slug);
            if ($exceptId !== null) {
                $q->where('id', '!=', $exceptId);
            }
            if (! $q->exists()) {
                return $slug;
            }
            $slug = $base.'-'.$i;
        }
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function currentOrder()
    {
        return $this->orders()
            ->whereIn('status', ['open', 'pending', 'confirmed', 'preparing', 'ready'])
            ->latest()
            ->first();
    }
}
