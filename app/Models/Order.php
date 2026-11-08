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
        'cod' => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
        'pay_at_store' => 'Pay at the store',
    ];

    /** Which delivery option each payment method works with. */
    public const PAYMENT_FULFILMENT = [
        'cod' => ['delivery'],
        'bank_transfer' => ['delivery', 'pickup'],
        'pay_at_store' => ['pickup'],
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
    ];

    protected $fillable = [
        'number', 'customer_name', 'phone', 'email', 'fulfilment', 'address', 'city',
        'payment_method', 'payment_status', 'paid_at', 'payment_reference', 'payment_slip',
        'status', 'subtotal', 'delivery_fee', 'total', 'notes', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
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

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /** Payment methods switched on in Admin → Settings. */
    public static function enabledPaymentMethods(): array
    {
        return array_values(array_filter(array_keys(self::PAYMENT_METHODS),
            fn ($method) => (bool) Setting::read("payment_{$method}_enabled")));
    }

    /** Methods a customer can pick for this delivery option and order value. */
    public static function allowedPaymentMethods(string $fulfilment, float $subtotal = 0): array
    {
        $codLimit = (float) Setting::read('cod_max_order');

        return array_values(array_filter(self::enabledPaymentMethods(), function ($method) use ($fulfilment, $subtotal, $codLimit) {
            if (! in_array($fulfilment, self::PAYMENT_FULFILMENT[$method], true)) {
                return false;
            }

            return ! ($method === 'cod' && $codLimit > 0 && $subtotal > $codLimit);
        }));
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
