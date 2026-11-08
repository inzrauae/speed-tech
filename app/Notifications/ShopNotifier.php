<?php

namespace App\Notifications;

use App\Models\Enquiry;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a plain-text e-mail to the shop for each new order / enquiry when
 * SHOP_NOTIFY_EMAIL is set. A mail failure never blocks the customer.
 */
class ShopNotifier
{
    public static function orderPlaced(Order $order): void
    {
        $lines = $order->items->map(fn ($i) => "- {$i->quantity} × {$i->product_name}".($i->oem_part_number ? " (P/N {$i->oem_part_number})" : '').' = '.money($i->line_total));

        self::send("New order {$order->number} – ".money($order->total), implode("\n", [
            "Order: {$order->number}",
            "Customer: {$order->customer_name}, {$order->phone}".($order->email ? ", {$order->email}" : ''),
            "Fulfilment: {$order->fulfilmentLabel()}".($order->address ? " – {$order->address}, {$order->city}" : ''),
            "Payment: {$order->paymentLabel()} ({$order->paymentStatusLabel()})",
            '',
            ...$lines,
            '',
            'Subtotal: '.money($order->subtotal),
            'Delivery: '.money($order->delivery_fee),
            'Total: '.money($order->total),
            $order->notes ? "\nNotes: {$order->notes}" : '',
            '',
            route('admin.orders.show', $order),
        ]));
    }

    public static function enquiryReceived(Enquiry $enquiry): void
    {
        self::send("New {$enquiry->typeLabel()} {$enquiry->reference}", implode("\n", array_filter([
            "Reference: {$enquiry->reference}",
            "Type: {$enquiry->typeLabel()}",
            "Customer: {$enquiry->name}, {$enquiry->phone}".($enquiry->email ? ", {$enquiry->email}" : ''),
            $enquiry->device ? "Device: {$enquiry->device}" : null,
            $enquiry->service ? "Service: {$enquiry->service}" : null,
            $enquiry->product ? "Product: {$enquiry->product->name}" : null,
            $enquiry->details ? 'Selection: '.collect($enquiry->details)->implode(' / ') : null,
            $enquiry->message ? "\n{$enquiry->message}\n" : null,
            route('admin.enquiries.show', $enquiry),
        ])));
    }

    public static function slipUploaded(Order $order): void
    {
        self::send("Payment slip uploaded for order {$order->number}", implode("\n", [
            "{$order->customer_name} uploaded a bank transfer slip for order {$order->number} (".money($order->total).').',
            'Check the slip and mark the order as paid:',
            route('admin.orders.show', $order),
        ]));
    }

    private static function send(string $subject, string $body): void
    {
        $to = config('speedtech.notify_email');
        if (! $to) {
            return;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (Throwable $e) {
            Log::warning('Shop notification e-mail failed: '.$e->getMessage());
        }
    }
}
