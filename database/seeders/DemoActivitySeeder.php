<?php

namespace Database\Seeders;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Sample orders and enquiries so the admin panel can be demonstrated.
 * Every record is marked as a sample (numbers start with DEMO-).
 * Remove them all with:  php artisan demo:clear
 * Does not change product stock. Safe to re-run (skips if samples exist).
 */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        if (Order::where('number', 'like', 'DEMO-%')->exists()) {
            $this->command?->info('Sample orders already exist; skipped.');

            return;
        }

        $products = Product::whereNotNull('price')->get()->keyBy('name');
        $line = function (string $name, int $qty) use ($products): ?array {
            $p = $products->get($name);

            return $p ? [
                'product_id' => $p->id, 'product_name' => $p->name, 'sku' => $p->sku,
                'oem_part_number' => $p->oem_part_number, 'unit_price' => $p->price,
                'quantity' => $qty, 'line_total' => $p->price * $qty,
            ] : null;
        };

        $orders = [
            ['Nimal Perera (sample)', '0771234501', 'delivery', 'cod', 'pending', 'Malabe', 0, [['15.6" FHD LCD display 30-pin', 1]], 'Please call before delivery.'],
            ['Kasuni Fernando (sample)', '0712345602', 'pickup', 'pay_at_store', 'confirmed', null, 1, [['Dell 41Wh battery WDX0R', 1], ['Dell Inspiron 3520 cooling fan', 1]], 'Need fitting as well.'],
            ['Ruwan Silva (sample)', '0763456703', 'delivery', 'bank_transfer', 'shipped', 'Kaduwela', 2, [['Laptop memory 8GB DDR4 3200', 2]], null],
            ['Dilani Jayasinghe (sample)', '0754567804', 'pickup', 'pay_at_store', 'ready', null, 3, [['Dell Inspiron 15 3520 keyboard (US)', 1]], null],
            ['Chamara Bandara (sample)', '0705678905', 'delivery', 'cod', 'completed', 'Battaramulla', 6, [['SATA solid state drive 512GB', 1], ['HDMI cable 1.5m', 1]], null],
            ['Ishara Wickramasinghe (sample)', '0786789006', 'pickup', 'pay_at_store', 'cancelled', null, 9, [['Wireless mouse', 1]], 'Customer changed mind.'],
        ];

        foreach ($orders as $i => [$name, $phone, $fulfilment, $payment, $status, $city, $daysAgo, $items, $notes]) {
            $lines = array_values(array_filter(array_map(fn ($item) => $line(...$item), $items)));
            if (! $lines) {
                continue;
            }
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $fee = $fulfilment === 'delivery' && $subtotal < (float) config('speedtech.free_delivery_over') ? (float) config('speedtech.delivery_fee') : 0;
            $when = now()->subDays($daysAgo)->subHours($i * 2);

            $order = new Order([
                'number' => 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'customer_name' => $name, 'phone' => $phone, 'email' => null,
                'fulfilment' => $fulfilment, 'payment_method' => $payment, 'status' => $status,
                'address' => $fulfilment === 'delivery' ? 'No. '.(10 + $i).', Sample Road' : null,
                'city' => $city, 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $subtotal + $fee,
                'notes' => $notes, 'admin_notes' => 'Sample order for demonstration. Remove with: php artisan demo:clear',
            ]);
            $order->created_at = $order->updated_at = $when;
            $order->save();
            $order->items()->createMany($lines);
        }

        $enquiries = [
            ['repair', 'Saman Kumara (sample)', '0771112201', 'Dell Inspiron 15 3511', 'Laptop & PC repairs', null, 'Screen flickers and goes black when I move the lid.', 'new', 0],
            ['repair', 'Tharushi Peiris (sample)', '0712223302', 'HP Pavilion 15', 'Data recovery', null, 'Hard drive clicking, need my photos back.', 'contacted', 1],
            ['part', 'Asanka Rathnayake (sample)', '0763334403', null, null, ['brand' => 'Lenovo', 'model' => 'ThinkPad E14', 'part' => 'Hinge'], 'Left hinge broken.', 'new', 0],
            ['product', 'Malsha Dissanayake (sample)', '0754445504', null, null, null, 'Do you have this in stock this week?', 'new', 2],
            ['general', 'Pradeep Gunawardena (sample)', '0705556605', null, null, null, 'Do you do on-site network setup for small offices?', 'closed', 5],
        ];
        $motherboard = Product::where('name', 'like', '%motherboard%')->first();

        foreach ($enquiries as $i => [$type, $name, $phone, $device, $service, $details, $message, $status, $daysAgo]) {
            $enquiry = new Enquiry([
                'reference' => 'DEMO-E'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'type' => $type, 'name' => $name, 'phone' => $phone, 'device' => $device,
                'service' => $service, 'details' => $details, 'message' => $message, 'status' => $status,
                'product_id' => $type === 'product' ? $motherboard?->id : null,
                'admin_notes' => 'Sample enquiry for demonstration.',
            ]);
            $enquiry->created_at = $enquiry->updated_at = now()->subDays($daysAgo)->subHours($i * 3);
            $enquiry->save();
        }

        $this->command?->info('Added '.count($orders).' sample orders and '.count($enquiries).' sample enquiries.');
    }
}
