<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'material_id',
        'name',
        'description',
        'width_cm',
        'height_cm',
        'color',
        'price',
        'stock',
        'image',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    protected $appends = [
        'availability',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getAvailabilityAttribute(): string
    {
        return $this->stock > 0 ? 'en stock' : 'a fabricar';
    }
}