<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InventoryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Product $ssd;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Storage']);
        $this->ssd = Product::create(['type' => 'accessory', 'category_id' => $cat->id, 'name' => 'SSD 512GB', 'sku' => 'SSD512', 'price' => 10000, 'stock_qty' => 5, 'reorder_level' => 2, 'cost_price' => 7000]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function checkout(array $overrides = [], int $qty = 2)
    {
        $this->post('/cart/items', ['product_id' => $this->ssd->id, 'quantity' => $qty]);

        return $this->post('/checkout', $overrides + [
            'customer_name' => 'Buyer', 'phone' => '0771234567',
            'fulfilment' => 'delivery', 'address' => '1 Main St', 'city' => 'Kaduwela', 'payment_method' => 'cod',
        ]);
    }

    // ---------------------------------------------------------------- inventory

    public function test_orders_and_cancellations_are_logged_as_stock_movements(): void
    {
        $this->checkout();
        $order = Order::firstOrFail();

        $sale = StockMovement::where('type', 'sale')->firstOrFail();
        $this->assertSame([-2, 3, $order->number], [$sale->quantity, $sale->balance_after, $sale->reference]);
        $this->assertSame(3, $this->ssd->fresh()->stock_qty);

        $this->actingAs($this->admin)->patch("/admin/orders/{$order->id}", ['status' => 'cancelled']);
        $returned = StockMovement::where('type', 'returned')->firstOrFail();
        $this->assertSame([2, 5], [$returned->quantity, $returned->balance_after]);
        $this->assertSame(5, $this->ssd->fresh()->stock_qty);
    }

    public function test_admin_receives_removes_and_counts_stock(): void
    {
        $this->actingAs($this->admin);
        $url = "/admin/inventory/{$this->ssd->slug}/adjust";

        $this->post($url, ['action' => 'receive', 'quantity' => 10, 'unit_cost' => 7500, 'reference' => 'INV-001'])->assertSessionHas('status');
        $this->assertSame(15, $this->ssd->fresh()->stock_qty);
        $this->assertEquals(7500, $this->ssd->fresh()->cost_price);

        $this->post($url, ['action' => 'remove', 'quantity' => 1, 'reason' => 'damaged', 'note' => 'Dropped']);
        $this->assertSame(14, $this->ssd->fresh()->stock_qty);
        $this->assertSame('damaged', StockMovement::latest('id')->first()->type);

        $this->post($url, ['action' => 'count', 'quantity' => 12]);
        $count = StockMovement::latest('id')->first();
        $this->assertSame(['adjustment', -2, 12], [$count->type, $count->quantity, $count->balance_after]);
        $this->assertSame($this->admin->id, $count->user_id);

        // Cannot remove more than is in stock.
        $this->post($url, ['action' => 'remove', 'quantity' => 99])->assertSessionHasErrors('quantity');
        $this->assertSame(12, $this->ssd->fresh()->stock_qty);
    }

    public function test_product_form_logs_opening_stock_and_edits(): void
    {
        $this->actingAs($this->admin);
        $cat = Category::first();
        $this->post('/admin/products', ['type' => 'accessory', 'name' => 'Mouse', 'category_id' => $cat->id, 'stock_qty' => 4, 'reorder_level' => 1, 'cost_price' => 1500, 'is_active' => 1]);
        $mouse = Product::where('name', 'Mouse')->firstOrFail();
        $this->assertSame(['opening', 4], [StockMovement::where('product_id', $mouse->id)->value('type'), $mouse->stock_qty]);

        $this->put("/admin/products/{$mouse->slug}", ['type' => 'accessory', 'name' => 'Mouse', 'category_id' => $cat->id, 'stock_qty' => 6, 'reorder_level' => 1, 'is_active' => 1]);
        $this->assertSame(6, $mouse->fresh()->stock_qty);
        $this->assertSame(2, StockMovement::where('product_id', $mouse->id)->latest('id')->value('quantity'));
    }

    public function test_inventory_pages_and_csv_export(): void
    {
        $this->actingAs($this->admin);
        $this->ssd->update(['stock_qty' => 1]);   // at/below reorder level 2

        $this->get('/admin/inventory')->assertOk()->assertSee('SSD 512GB')->assertSee('Need reorder');
        $this->get('/admin/inventory?stock=reorder')->assertOk()->assertSee('SSD512');
        $this->get('/admin/inventory/movements')->assertOk();
        $this->get('/admin')->assertOk()->assertSee('reorder at 2');

        $csv = $this->get('/admin/inventory/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('SSD 512GB', $csv);
        $this->assertStringContainsString('Reorder level', $csv);
    }

    // ---------------------------------------------------------------- payments

    public function test_checkout_only_offers_enabled_methods_that_suit_the_delivery_option(): void
    {
        // COD is for delivery only.
        $this->checkout(['fulfilment' => 'pickup', 'address' => null, 'city' => null, 'payment_method' => 'cod'])->assertSessionHasErrors('payment_method');

        // Switched off in settings.
        Setting::write(['payment_cod_enabled' => false]);
        $this->checkout()->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());

        // COD limit.
        Setting::write(['payment_cod_enabled' => true, 'cod_max_order' => 5000]);
        $this->checkout()->assertSessionHasErrors('payment_method');
        $this->assertNotContains('cod', Order::allowedPaymentMethods('delivery', 20000));
        $this->assertContains('cod', Order::allowedPaymentMethods('delivery', 4000));
    }

    public function test_checkout_page_shows_bank_details_and_settings_change_delivery_fee(): void
    {
        Setting::write(['bank_name' => 'Commercial Bank', 'bank_account_number' => '8001234567', 'delivery_fee' => 600, 'free_delivery_over' => 0]);
        $this->post('/cart/items', ['product_id' => $this->ssd->id]);

        $this->get('/checkout')->assertOk()
            ->assertSee('Commercial Bank')->assertSee('8001234567')
            ->assertSee('data-payment="bank_transfer"', false)->assertSee('data-payment="cod"', false)
            ->assertSee('Rs. 600');

        $this->checkout(['payment_method' => 'bank_transfer'], 1);
        $this->assertEquals(600, (float) Order::first()->delivery_fee);
    }

    public function test_bank_transfer_slip_upload_and_admin_mark_paid(): void
    {
        Storage::fake('local');
        Setting::write(['bank_account_number' => '8001234567']);
        $response = $this->checkout(['payment_method' => 'bank_transfer']);
        $order = Order::firstOrFail();
        $this->assertSame('unpaid', $order->payment_status);

        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('Complete your bank transfer')->assertSee('8001234567')->assertSee($order->number);

        // Unsigned upload is refused.
        $this->post("/orders/{$order->number}/slip", ['slip' => UploadedFile::fake()->image('slip.jpg')])->assertForbidden();

        $this->post(URL::signedRoute('orders.slip', $order), ['slip' => UploadedFile::fake()->create('slip.pdf', 200, 'application/pdf')])
            ->assertRedirect();
        $order->refresh();
        $this->assertNotNull($order->payment_slip);
        Storage::disk('local')->assertExists($order->payment_slip);

        // Only admins can see the slip.
        $this->get("/admin/orders/{$order->id}/slip")->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/slip")->assertOk();
        $this->get('/admin?')->assertSee('Slip uploaded');
        $this->get('/admin/orders?slip=1')->assertSee($order->number);

        $this->patch("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid', 'payment_reference' => 'BOC-998'])->assertSessionHas('status');
        $order->refresh();
        $this->assertSame(['paid', 'BOC-998'], [$order->payment_status, $order->payment_reference]);
        $this->assertNotNull($order->paid_at);
    }

    public function test_slip_upload_rejects_wrong_files_and_non_bank_orders(): void
    {
        Storage::fake('local');
        $this->checkout(['payment_method' => 'cod']);
        $order = Order::firstOrFail();
        $this->post(URL::signedRoute('orders.slip', $order), ['slip' => UploadedFile::fake()->image('s.jpg')])->assertNotFound();

        $order->update(['payment_method' => 'bank_transfer']);
        $this->post(URL::signedRoute('orders.slip', $order), ['slip' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('slip');
    }

    public function test_settings_page_validates_and_saves(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/settings')->assertOk()->assertSee('Cash on delivery');

        $base = ['delivery_fee' => 450, 'free_delivery_over' => 25000];
        $this->put('/admin/settings', $base + ['payment_cod_enabled' => 0, 'payment_bank_transfer_enabled' => 0, 'payment_pay_at_store_enabled' => 0])
            ->assertSessionHasErrors('payment');
        $this->put('/admin/settings', $base + ['payment_bank_transfer_enabled' => 1])->assertSessionHasErrors('bank_account_number');

        $this->put('/admin/settings', ['payment_cod_enabled' => 1, 'payment_bank_transfer_enabled' => 1, 'bank_name' => 'HNB', 'bank_account_number' => '123456', 'delivery_fee' => 500] + $base)
            ->assertSessionHas('status');
        Setting::flush();
        $this->assertSame('HNB', Setting::read('bank_name'));
        $this->assertEquals(500, Setting::read('delivery_fee'));
        $this->assertFalse((bool) Setting::read('payment_pay_at_store_enabled'));
        $this->assertSame(['cod', 'bank_transfer'], Order::enabledPaymentMethods());
    }
}
