<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_status_id',
        'locality_id',
        'total',
        'deposit_percentage',
        'deposit_amount',
        'requires_manufacturing',
        'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'deposit_percentage' => 'integer',
        'requires_manufacturing' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
