<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AdminActionLog::class);
    }
}