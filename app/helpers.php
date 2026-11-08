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
