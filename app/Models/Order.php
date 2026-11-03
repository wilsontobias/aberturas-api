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
        'street',
        'street_number',
        'total',
        'deposit_percentage',
        'deposit_amount',
        'balance_amount',
        'requires_manufacturing',
        'address_notes',
        'estimated_ship_date',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'deposit_percentage' => 'integer',
        'requires_manufacturing' => 'boolean',
        'estimated_ship_date' => 'date:Y-m-d',
        'confirmed_at' => 'datetime',
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

        if ($statusName === 'confirmed') {
            $data['confirmed_at'] = now();

            if (!$this->estimated_ship_date) {
                $data['estimated_ship_date'] = self::nextShipDate($this->requires_manufacturing);
            }
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

    /**
     * Cuando se confirma un pedido con productos para fabricar, empieza la fabricación:
     * el pedido pasa a in_production y los productos pendientes también.
     */
    public function startProduction(?int $userId = null): void
    {
        if (!$this->requires_manufacturing) {
            return;
        }

        $this->changeStatus('in_production', $userId, 'Empezó la fabricación.');

        $this->items()
            ->where('item_status_id', ItemStatus::where('name', 'pending')->value('id'))
            ->update(['item_status_id' => ItemStatus::where('name', 'in_production')->value('id')]);
    }

    /**
     * Cuando TODOS los productos tienen el mismo estado, el pedido pasa a ese estado:
     * in_production -> in_production, done -> finished, packed -> packed, shipped -> shipped.
     */
    public function syncStatusFromItems(?int $userId = null): void
    {
        $current = OrderStatus::where('id', $this->order_status_id)->value('name');

        $flow = [
            'confirmed' => ['in_production', 'packed'],
            'in_production' => ['finished', 'packed'],
            'finished' => ['packed'],
            'packed' => ['shipped'],
        ];

        $names = $this->items()->with('status')->get()->pluck('status.name')->unique();

        if ($names->count() !== 1) {
            return;
        }

        $target = ['in_production' => 'in_production', 'done' => 'finished', 'packed' => 'packed', 'shipped' => 'shipped'][$names->first()] ?? null;

        if ($target && in_array($target, $flow[$current] ?? [], true)) {
            $this->changeStatus($target, $userId, 'Todos los productos están en el mismo estado.');
        }
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
