<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}