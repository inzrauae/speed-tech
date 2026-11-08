<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_create_products_orders_and_enquiries_and_clear_removes_only_samples(): void
    {
        $this->seed([CatalogStructureSeeder::class, DemoProductSeeder::class, DemoActivitySeeder::class]);

        $this->assertGreaterThan(20, Product::count());
        $this->assertSame(6, Order::where('number', 'like', 'DEMO-%')->count());
        $this->assertSame(5, Enquiry::where('reference', 'like', 'DEMO-%')->count());
        $stock = Product::sum('stock_qty');

        // Admin screens render with the sample data.
        $admin = \App\Models\User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertSee('DEMO-0001');
        $this->get('/admin/orders')->assertOk()->assertSee('Nimal Perera (sample)');
        $this->get('/admin/orders/'.Order::where('number', 'DEMO-0002')->value('id'))->assertOk()->assertSee('Dell 41Wh battery WDX0R');
        $this->get('/admin/enquiries')->assertOk()->assertSee('DEMO-E001');
        $this->get('/admin/enquiries/'.Enquiry::where('reference', 'DEMO-E003')->value('id'))->assertOk()->assertSee('ThinkPad E14');
        auth()->logout();

        // Re-running does not duplicate.
        $this->seed(DemoActivitySeeder::class);
        $this->assertSame(6, Order::count());
        $this->assertSame($stock, (int) Product::sum('stock_qty'));

        // A real order survives demo:clear.
        Order::create(['number' => 'ST261108-REAL1', 'customer_name' => 'Real', 'phone' => '0770000000', 'fulfilment' => 'pickup', 'payment_method' => 'pay_at_store', 'subtotal' => 100, 'total' => 100]);

        $this->artisan('demo:clear')->expectsConfirmation('Delete 6 sample orders and 5 sample enquiries?', 'yes')->assertSuccessful();

        $this->assertSame(['ST261108-REAL1'], Order::pluck('number')->all());
        $this->assertSame(0, Enquiry::count());
    }
}
