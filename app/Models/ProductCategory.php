<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
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
        return $this->hasMany(Product::class, 'category_id');
    }
}