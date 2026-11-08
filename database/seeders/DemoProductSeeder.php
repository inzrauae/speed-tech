<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Illustrative products so the storefront is not empty on day one.
 * Prices, stock and part numbers are SAMPLE values — replace them in /admin
 * before going live, or skip this seeder in production.
 */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $cat = Category::pluck('id', 'slug');
        $part = PartCategory::pluck('id', 'slug');

        $accessories = [
            ['Everyday performance laptop', 'laptops', 'assets/laptop.jpg', 'WORK & STUDY', 'A reliable partner for your everyday tasks.', 'Tell us your preferred brand, screen size, memory, storage and budget. We will help you choose from the available models.', null, 0, true],
            ['SATA solid state drive 512GB', 'storage', null, 'STORAGE UPGRADE', 'Faster starts. More room for what matters.', 'Ask about available SSD capacities and brands. We will check the drive size, interface and compatibility with your laptop or desktop before recommending an upgrade.', 14500, 12, true],
            ['Laptop memory 8GB DDR4 3200', 'ram', 'assets/ram.jpg', 'SMOOTHER MULTITASKING', 'Give your laptop a little more breathing room.', 'Share your laptop model and current memory configuration. We will confirm the supported RAM generation, speed, capacity and available slots.', 9800, 20, true],
            ['Desktop processor', 'pc-components', 'assets/processor.jpg', 'BUILD & UPGRADE', 'The right foundation for your next PC build.', 'Tell us how you use your PC and your budget. We will check motherboard socket, chipset and cooling compatibility before suggesting an available processor.', null, 0, true],
            ['Wireless mouse', 'accessories', 'assets/mouse.jpg', 'DESK ESSENTIAL', 'Simple, comfortable control. Less desk clutter.', 'Wireless 2.4GHz mouse with USB nano receiver. Ask us about other available models.', 2950, 25, true],
            ['Mechanical keyboard', 'accessories', 'assets/keyboard.jpg', 'MAKE IT YOUR SETUP', 'A better feel for work, play and everything between.', 'Choose your preferred layout, switch type and connectivity. We will share available keyboard models and current pricing.', null, 0, true],
            ['Over-ear headphones', 'accessories', 'assets/headphones.jpg', 'FOCUS & PLAY', 'Tune in to your calls, work and favourite playlists.', 'Ask about available wired and wireless headphones, microphone options and device compatibility.', 6500, 4, true],
            ['Performance laptop', 'laptops', 'assets/laptop.jpg', 'CREATE & DO MORE', 'Find the power your next project needs.', 'Share the applications or games you use, your preferred screen size and budget. We will recommend available models with suitable graphics, memory and storage.', null, 0, true],
            ['65W USB-C laptop charger', 'chargers-adapters', null, 'POWER UP', 'Universal USB-C Power Delivery charger.', 'Supports USB-C PD laptops up to 65W. Check your laptop supports USB-C charging before ordering.', 7900, 8, false],
            ['HDMI cable 1.5m', 'cables', null, 'CONNECT', '4K-ready HDMI cable for monitors and TVs.', 'HDMI 2.0, 1.5 metre, gold-plated connectors.', 1200, 30, false],
        ];
        foreach ($accessories as [$name, $slug, $image, $tag, $summary, $description, $price, $stock, $featured]) {
            Product::updateOrCreate(['name' => $name], [
                'type' => Product::TYPE_ACCESSORY, 'category_id' => $cat[$slug] ?? null, 'image' => $image,
                'tag' => $tag, 'summary' => $summary, 'description' => $description, 'price' => $price,
                'stock_qty' => $stock, 'warranty' => $price ? '6 months' : null, 'is_featured' => $featured,
            ]);
        }

        // [name, part slug, OEM P/N, spec, connector, size, price, stock, warranty, fits: [model name => generation label|null(all)]]
        $spareParts = [
            ['15.6" FHD LCD display 30-pin', 'lcd-led-display', 'NV156FHM-N48', '15.6", FHD 1920×1080, IPS, matte', '30-pin eDP', '15.6"', 15000, 5, '3 months', ['Inspiron 15 3520' => null, 'Inspiron 15 3511' => null, 'IdeaPad 3 15' => null, 'Aspire 5 A515' => null, 'VivoBook 15 X515' => null]],
            ['15.6" HD LCD display 30-pin', 'lcd-led-display', 'NT156WHM-N42', '15.6", HD 1366×768, TN, glossy', '30-pin eDP', '15.6"', 11500, 3, '3 months', ['250 G8' => null, 'Aspire 3 A315' => null, 'Vostro 3510' => null]],
            ['Dell Inspiron 15 3520 keyboard (US)', 'keyboard', '0J6MRV', 'US layout, black, non-backlit', null, null, 6500, 6, '3 months', ['Inspiron 15 3520' => null, 'Inspiron 15 3511' => null, 'Vostro 3510' => null]],
            ['Dell 41Wh battery WDX0R', 'battery', 'WDX0R', '11.4V, 3-cell, 41Wh', null, null, 12500, 4, '6 months', ['Inspiron 15 3520' => null, 'Inspiron 15 3511' => null, 'Vostro 3510' => null]],
            ['HP HT03XL battery', 'battery', 'HT03XL', '11.4V, 3-cell, 41.9Wh', null, null, 11000, 2, '6 months', ['250 G8' => null, 'Pavilion 15' => '2019–2020 (cs series)']],
            ['Lenovo L19C3PF0 battery', 'battery', 'L19C3PF0', '11.25V, 3-cell, 35Wh', null, null, 10500, 0, '6 months', ['IdeaPad 3 15' => null]],
            ['Dell Inspiron 3520 cooling fan', 'cooling-fan', '0WV6C8', '5V DC, 4-pin', '4-pin', null, 3800, 7, '3 months', ['Inspiron 15 3520' => null]],
            ['HP Pavilion 15-eg cooling fan', 'cooling-fan', 'M08901-001', '5V DC, 4-pin', '4-pin', null, 4200, 3, '3 months', ['Pavilion 15' => '2021–2023 (eg series)']],
            ['Dell Inspiron 3520 hinge set (L+R)', 'hinge', '0F6NY2', 'Left and right hinges', null, null, 4500, 5, '3 months', ['Inspiron 15 3520' => null, 'Inspiron 15 3511' => null]],
            ['Lenovo IdeaPad 3 DC power jack', 'dc-power-jack', '5C10S30069', 'DC-in jack with cable', null, null, 2500, 10, '3 months', ['IdeaPad 3 15' => null]],
            ['Dell Inspiron 3520 display cable', 'display-cable', '0V3C9X', 'eDP cable, 30-pin, FHD', '30-pin eDP', null, 3200, 4, '3 months', ['Inspiron 15 3520' => null]],
            ['Dell Inspiron 3520 palmrest with keyboard', 'palmrest-housing', '0M4F5P', 'Palmrest top cover, silver, with US keyboard', null, null, 18500, 1, '3 months', ['Inspiron 15 3520' => null]],
            ['Dell Inspiron 3520 speakers (pair)', 'speaker', '0K1X9T', 'Left and right speakers', null, null, 3500, 0, '3 months', ['Inspiron 15 3520' => null]],
            ['HP Pavilion 15 touchpad', 'touchpad', 'L63584-001', 'Clickpad with cable', null, null, 5800, 2, '3 months', ['Pavilion 15' => null]],
            ['Lenovo ThinkPad T480 keyboard (US, backlit)', 'keyboard', '01HX459', 'US layout, backlit, with TrackPoint', null, null, 9500, 3, '3 months', ['ThinkPad T480' => null]],
            ['MacBook Air M1 A2337 battery', 'battery', 'A2389', '11.4V, 49.9Wh', null, null, 24500, 2, '6 months', ['MacBook Air 13" M1 (A2337)' => null]],
            ['HD webcam module', 'webcam', 'CN0FM4V3', '720p HD camera board', null, null, null, 0, '3 months', ['Latitude 5420' => null]],
            ['Dell Inspiron 15 3520 motherboard (i5-1235U)', 'motherboard', '0K7HGR', 'Intel Core i5-1235U, UMA graphics', null, null, null, 0, '3 months', ['Inspiron 15 3520' => null]],
        ];

        $models = LaptopModel::with('generations')->get()->keyBy('name');
        foreach ($spareParts as [$name, $slug, $pn, $spec, $connector, $size, $price, $stock, $warranty, $fits]) {
            $product = Product::updateOrCreate(['name' => $name], [
                'type' => Product::TYPE_SPARE_PART, 'part_category_id' => $part[$slug] ?? null,
                'oem_part_number' => $pn, 'sku' => 'SP-'.$pn, 'specification' => $spec, 'connector_type' => $connector,
                'size' => $size, 'price' => $price, 'stock_qty' => $stock, 'warranty' => $warranty,
                'summary' => $spec, 'tag' => 'GENUINE / OEM-GRADE',
                'description' => 'We confirm the OEM part number, connector and compatibility with your exact laptop before fitting or dispatch. Bring your laptop in and we can fit it for you.',
            ]);

            $generationIds = [];
            foreach ($fits as $modelName => $label) {
                $model = $models->get($modelName);
                if (! $model) {
                    continue;
                }
                $generations = $label ? $model->generations->where('label', $label) : $model->generations;
                $generationIds = array_merge($generationIds, $generations->pluck('id')->all());
            }
            $product->generations()->sync($generationIds);
        }
    }
}
