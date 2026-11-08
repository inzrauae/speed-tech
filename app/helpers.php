<?php

if (! function_exists('money')) {
    /** Format an amount in Sri Lankan rupees: "Rs. 15,000" (cents shown only when present). */
    function money(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return config('speedtech.currency').' '.number_format($amount, $decimals);
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(string $message = ''): string
    {
        $url = 'https://wa.me/'.config('speedtech.whatsapp');

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}

if (! function_exists('delivery_text')) {
    /** "Delivery is Rs. 450 and free on orders over Rs. 25,000." from Admin → Payments & delivery. */
    function delivery_text(): string
    {
        $fee = (float) \App\Models\Setting::read('delivery_fee');
        $freeOver = (float) \App\Models\Setting::read('free_delivery_over');

        if ($fee <= 0) {
            return 'Delivery is free.';
        }

        return 'Delivery is '.money($fee).($freeOver > 0 ? ' and free on orders over '.money($freeOver) : '').'.';
    }
}
