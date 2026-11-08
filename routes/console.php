<?php

use App\Models\Enquiry;
use App\Models\Order;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
