<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'estimated_ship_date',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'deposit_percentage' => 'integer',
        'requires_manufacturing' => 'boolean',
        'estimated_ship_date' => 'date:Y-m-d',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function changeStatus(string $statusName, ?int $userId = null, ?string $note = null): void
    {
        $statusId = OrderStatus::where('name', $statusName)->value('id');
        $data = ['order_status_id' => $statusId];

        if ($statusName === 'confirmed' && !$this->estimated_ship_date) {
            $data['estimated_ship_date'] = self::nextShipDate($this->requires_manufacturing);
        }

        if ($statusName === 'shipped') {
            $data['shipped_at'] = now();
        }

        if ($statusName === 'delivered') {
            $data['delivered_at'] = now();
        }

        $this->update($data);

        $this->statusHistory()->create([
            'order_status_id' => $statusId,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    public static function nextShipDate(bool $requiresManufacturing, ?Carbon $from = null): Carbon
    {
        $date = ($from ?? now())->copy()->startOfDay();

        if ($requiresManufacturing) {
            $date->addWeeks(3);
        }

        do {
            $date->addDay();
        } while (!$date->isMonday() && !$date->isThursday());

        return $date;
    }
}
