<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'company_id',
        'name',
        'abbreviation',
        'type',
        'is_base_unit',
    ];

    /**
     * Get the company that owns this unit.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all ingredients using this unit.
     */
    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'base_unit_id');
    }
}

