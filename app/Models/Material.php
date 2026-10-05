<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'material_id');
    }
}