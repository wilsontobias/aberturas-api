<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'create_orders',
        'view_own_orders',
        'view_all_orders',
        'verify_payments',
        'update_item_status',
        'edit_ship_date',
        'register_balance_payment',
    ];

    protected $casts = [
        'create_orders' => 'boolean',
        'view_own_orders' => 'boolean',
        'view_all_orders' => 'boolean',
        'verify_payments' => 'boolean',
        'update_item_status' => 'boolean',
        'edit_ship_date' => 'boolean',
        'register_balance_payment' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultsFor(string $userType): array
    {
        if ($userType === 'seller') {
            return [
                'create_orders' => false,
                'view_own_orders' => true,
                'view_all_orders' => true,
                'verify_payments' => true,
                'update_item_status' => true,
                'edit_ship_date' => true,
                'register_balance_payment' => true,
            ];
        }

        return [
            'create_orders' => true,
            'view_own_orders' => true,
            'view_all_orders' => false,
            'verify_payments' => false,
            'update_item_status' => false,
            'edit_ship_date' => false,
            'register_balance_payment' => false,
        ];
    }
}