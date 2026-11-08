<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\Order;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_guests_and_non_admins_are_kept_out(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_can_log_in(): void
    {
        $this->post('/admin/login', ['email' => $this->admin->email, 'password' => 'password'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_non_admin_cannot_log_in(): void
    {
        $customer = User::factory()->create();
        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/models', '/admin/models/create',
            '/admin/catalog/brands', '/admin/catalog/part-categories', '/admin/catalog/categories',
            '/admin/orders', '/admin/enquiries', '/admin/account'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/catalog/unknown')->assertNotFound();
    }

    public function test_admin_creates_spare_part_with_compatibility_and_image(): void
    {
        $brand = Brand::create(['name' => 'Lenovo']);
        $model = LaptopModel::create(['brand_id' => $brand->id, 'name' => 'IdeaPad 3']);
        $gen = $model->generations()->create(['label' => 'Gen 6']);
        $type = PartCategory::create(['name' => 'Keyboard']);

        $this->actingAs($this->admin)->post('/admin/products', [
            'type' => 'spare_part', 'name' => 'IdeaPad 3 keyboard', 'part_category_id' => $type->id,
            'oem_part_number' => 'SN20', 'price' => '7500', 'stock_qty' => 4, 'is_active' => 1,
            'generation_ids' => [$gen->id],
            'image' => UploadedFile::fake()->image('kb.jpg', 400, 300),
        ])->assertRedirect();

        $product = Product::where('name', 'IdeaPad 3 keyboard')->firstOrFail();
        $this->assertSame([$gen->id], $product->generations->pluck('id')->all());
        $this->assertNull($product->category_id);
        $this->assertStringStartsWith('uploads/products/', $product->image);
        $this->assertFileExists(public_path($product->image));

        $this->delete('/admin/products/'.$product->slug)->assertRedirect('/admin/products');
        $this->assertFileDoesNotExist(public_path($product->image));
        File::deleteDirectory(public_path('uploads/products'), preserve: true);
    }

    public function test_accessory_requires_category(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'type' => 'accessory', 'name' => 'Mouse', 'stock_qty' => 1,
        ])->assertSessionHasErrors('category_id');

        $cat = Category::create(['name' => 'Accessories']);
        $this->post('/admin/products', ['type' => 'accessory', 'name' => 'Mouse', 'stock_qty' => 1, 'category_id' => $cat->id])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_taxonomy_crud_and_in_use_protection(): void
    {
        $this->actingAs($this->admin)->post('/admin/catalog/part-categories', ['name' => 'Hinge', 'sort_order' => 1, 'is_active' => 1]);
        $hinge = PartCategory::where('name', 'Hinge')->firstOrFail();
        $this->assertSame('hinge', $hinge->slug);

        Product::create(['type' => 'spare_part', 'name' => 'Hinge set', 'part_category_id' => $hinge->id]);
        $this->delete("/admin/catalog/part-categories/{$hinge->id}")->assertSessionHas('error');
        $this->assertModelExists($hinge);
    }

    public function test_cancelling_an_order_returns_stock(): void
    {
        $product = Product::create(['type' => 'accessory', 'name' => 'SSD', 'price' => 10000, 'stock_qty' => 3]);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/checkout', ['customer_name' => 'A', 'phone' => '0771234567', 'fulfilment' => 'pickup', 'payment_method' => 'pay_at_store']);
        $order = Order::firstOrFail();
        $this->assertSame(1, $product->fresh()->stock_qty);

        $this->actingAs($this->admin)->patch("/admin/orders/{$order->id}", ['status' => 'cancelled'])->assertSessionHas('status');
        $this->assertSame(3, $product->fresh()->stock_qty);

        $this->patch("/admin/orders/{$order->id}", ['status' => 'confirmed']);
        $this->assertSame(1, $product->fresh()->stock_qty);
    }
}
