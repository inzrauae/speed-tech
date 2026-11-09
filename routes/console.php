<?php

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Services\SkuGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:clear', function () {
    $orders = Order::where('number', 'like', 'DEMO-%')->count();
    $enquiries = Enquiry::where('reference', 'like', 'DEMO-%')->count();

    if (! $this->confirm("Delete {$orders} sample orders and {$enquiries} sample enquiries?", true)) {
        return;
    }

    Order::where('number', 'like', 'DEMO-%')->delete();        // order items cascade
    Enquiry::where('reference', 'like', 'DEMO-%')->delete();
    $this->info('Sample orders and enquiries removed. Real orders were not touched.');
})->purpose('Remove the sample (DEMO-) orders and enquiries');

Artisan::command('products:codes {--all : Replace every existing code, not just fill missing ones}', function (SkuGenerator $generator) {
    $query = Product::orderBy('id');
    if (! $this->option('all')) {
        $query->where(fn ($q) => $q->whereNull('sku')->orWhere('sku', ''));
    }
    $products = $query->get();

    if ($products->isEmpty()) {
        $this->info('Every product already has a code.');

        return;
    }
    if ($this->option('all') && ! $this->confirm("Replace the codes of all {$products->count()} products? Codes on past orders are kept as they were.", true)) {
        return;
    }

    if ($this->option('all')) {
        Product::whereKey($products->modelKeys())->update(['sku' => null]); // free the old codes first
    }

    $rows = [];
    foreach ($products as $product) {
        $product->sku = $generator->for($product);
        $product->saveQuietly();
        $rows[] = [$product->sku, $product->oem_part_number ?: '-', Str::limit($product->name, 50)];
    }
    $this->table(['Code', 'OEM P/N', 'Product'], $rows);
    $this->info(count($rows).' product codes generated.');
})->purpose('Generate product codes (SKUs) like LCD-00012 for products that have none');
