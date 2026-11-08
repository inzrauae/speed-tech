<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Key/value shop settings editable in /admin/settings.
 * Falls back to config/speedtech.php when a value has not been saved yet.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'shop-settings';

    /** Defaults used until the admin saves the settings page. */
    public static function defaults(): array
    {
        return [
            'payment_cod_enabled' => true,
            'payment_bank_transfer_enabled' => true,
            'payment_pay_at_store_enabled' => true,
            'bank_name' => '',
            'bank_account_name' => 'SpeedTech IT Solutions',
            'bank_account_number' => '',
            'bank_branch' => '',
            'bank_instructions' => 'Please use your order number as the payment reference and upload the payment slip, or send it to us on WhatsApp.',
            'cod_max_order' => 0,   // 0 = no limit
            'delivery_fee' => (float) config('speedtech.delivery_fee'),
            'free_delivery_over' => (float) config('speedtech.free_delivery_over'),
        ];
    }

    private static ?array $memo = null;

    public static function allValues(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            // If the table is missing the query throws, so nothing empty gets cached.
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
                ->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all());
        } catch (Throwable) {
            return self::defaults();   // database not ready yet (e.g. before migrations)
        }

        return self::$memo = array_merge(self::defaults(), $saved);
    }

    /** Forget cached values (after saving, and between tests). */
    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        return self::allValues()[$key] ?? $default;
    }

    public static function write(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }
        self::flush();
    }

    public static function bankDetails(): array
    {
        return array_filter([
            'Bank' => self::read('bank_name'),
            'Account name' => self::read('bank_account_name'),
            'Account number' => self::read('bank_account_number'),
            'Branch' => self::read('bank_branch'),
        ]);
    }
}
