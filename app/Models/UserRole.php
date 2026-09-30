<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'user_type_id',
        'status',
        'is_owner',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_owner' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }
}