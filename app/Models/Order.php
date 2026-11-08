<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'ready' => 'Ready for pickup',
        'shipped' => 'Out for delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const FULFILMENT = [
        'pickup' => 'Collect from our Kaduwela store',
        'delivery' => 'Island-wide delivery',
    ];

    public const PAYMENT_METHODS = [
        'pay_at_store' => 'Pay at the store',
        'cod' => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
    ];

    protected $fillable = [
        'number', 'customer_name', 'phone', 'email', 'fulfilment', 'address', 'city',
        'payment_method', 'status', 'subtotal', 'delivery_fee', 'total', 'notes', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function fulfilmentLabel(): string
    {
        return self::FULFILMENT[$this->fulfilment] ?? $this->fulfilment;
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    /** Statuses that still hold stock. */
    public function holdsStock(): bool
    {
        return $this->status !== 'cancelled';
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'ST'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (static::where('number', $number)->exists());

        return $number;
    }
}
