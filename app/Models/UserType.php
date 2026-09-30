<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }
}