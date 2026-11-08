<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Enquiry;
use App\Models\LaptopModel;
use App\Models\Order;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Product $battery;
    private Product $display;
    private Product $onRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $dell = Brand::create(['name' => 'Dell']);
        $hp = Brand::create(['name' => 'HP']);
        $inspiron = LaptopModel::create(['brand_id' => $dell->id, 'name' => 'Inspiron 15 3520']);
        $pavilion = LaptopModel::create(['brand_id' => $hp->id, 'name' => 'Pavilion 15']);
        $gen = $inspiron->generations()->create(['label' => '2022–2024', 'year_from' => 2022, 'year_to' => 2024]);
        $hpGen = $pavilion->generations()->create(['label' => '2021–2023']);

        $batteryType = PartCategory::create(['name' => 'Battery']);
        $displayType = PartCategory::create(['name' => 'LCD / LED Display']);

        $this->battery = Product::create(['type' => 'spare_part', 'part_category_id' => $batteryType->id, 'name' => 'Dell 41Wh battery', 'oem_part_number' => 'WDX0R', 'price' => 12500, 'stock_qty' => 3]);
        $this->battery->generations()->attach($gen);

        $this->display = Product::create(['type' => 'spare_part', 'part_category_id' => $displayType->id, 'name' => 'HP display', 'price' => 15000, 'stock_qty' => 1]);
        $this->display->generations()->attach($hpGen);

        $this->onRequest = Product::create(['type' => 'spare_part', 'part_category_id' => $displayType->id, 'name' => 'Inspiron logic board', 'price' => null, 'stock_qty' => 0]);
        $this->onRequest->generations()->attach($gen);
    }

    public function test_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Find a part for your laptop.');
        $this->get('/shop')->assertOk();
        $this->get('/spare-parts')->assertOk()->assertSee('Dell');
        $this->get('/products/'.$this->battery->slug)->assertOk()->assertSee('WDX0R')->assertSee('Inspiron 15 3520');
        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
        $this->get('/repairs')->assertOk()->assertSee('Motherboard repair')->assertSee('Data recovery')
            ->assertSee('Will I know the price before you start?')->assertSee('data-service="PC assembly"', false);
    }

    public function test_home_content_sections_and_benefits_strip(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Make your laptop last longer')
            ->assertSee('Questions before you buy')
            ->assertSee('Do you deliver?')
            ->assertSee("Can't find your part?", false)
            ->assertSee('Right part, first time')
            ->assertSee('Shop parts for your laptop brand')
            ->assertSee('All Dell parts')
            ->assertDontSee('Real people. Right here.')
            ->assertSee('id="contact"', false);

        // Benefits strip is sitewide.
        $this->get('/shop')->assertSee('Island-wide delivery');
    }

    public function test_about_and_contact_pages(): void
    {
        $this->get('/about')->assertOk()->assertSee('Good tech deserves a second chance.')->assertSee('What you can expect from us');
        $this->get('/contact')->assertOk()->assertSee('Send us a message')->assertSee('output=embed', false)
            ->assertSee('class="mobile-actions"', false)->assertSee('href="tel:', false);
    }

    public function test_contact_form_creates_general_enquiry(): void
    {
        $this->postJson('/enquiries', ['type' => 'general', 'name' => 'Sunil', 'phone' => '0771234567'])
            ->assertStatus(422)->assertJsonValidationErrors('message');

        $this->postJson('/enquiries', ['type' => 'general', 'name' => 'Sunil', 'phone' => '0771234567', 'message' => 'Do you sell HP chargers?'])
            ->assertCreated();
        $this->assertSame('general', Enquiry::latest('id')->first()->type);
    }

    public function test_repair_service_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Expert repair services')->assertSee(route('repairs.show', 'data-recovery'), false);

        foreach (config('speedtech.repair_services') as $service) {
            $this->get('/repairs/'.$service['slug'])->assertOk()
                ->assertSee($service['name'])->assertSee($service['signs'][0])
                ->assertSee('data-service="'.e($service['name']).'"', false);
        }

        $this->get('/repairs/not-a-service')->assertNotFound();
    }

    public function test_spare_part_finder_filters_by_brand_model_year_and_part(): void
    {
        $dell = Brand::where('name', 'Dell')->first();
        $model = LaptopModel::where('name', 'Inspiron 15 3520')->first();
        $gen = $model->generations->first();

        $this->get("/spare-parts?brand={$dell->id}&model={$model->id}&generation={$gen->id}")
            ->assertOk()->assertSee('Dell 41Wh battery')->assertSee('Inspiron logic board')->assertDontSee('HP display');

        $this->get("/spare-parts?brand={$dell->id}&part={$this->battery->part_category_id}")
            ->assertSee('Dell 41Wh battery')->assertDontSee('Inspiron logic board');

        $this->get('/spare-parts?q=WDX0R')->assertSee('Dell 41Wh battery')->assertDontSee('HP display');
    }

    public function test_finder_api_cascades(): void
    {
        $dell = Brand::where('name', 'Dell')->first();
        $model = LaptopModel::where('name', 'Inspiron 15 3520')->first();

        $this->getJson("/api/finder/models?brand={$dell->id}")->assertOk()->assertJson([['name' => 'Inspiron 15 3520']]);
        $this->getJson("/api/finder/generations?model={$model->id}")->assertOk()->assertJson([['label' => '2022–2024']]);
        $this->getJson("/api/finder/parts?brand={$dell->id}")->assertOk()
            ->assertJsonFragment(['name' => 'Battery', 'count' => 1])
            ->assertJsonFragment(['name' => 'LCD / LED Display', 'count' => 1]);
    }

    public function test_cart_caps_quantity_at_stock_and_rejects_items_without_price(): void
    {
        $this->postJson('/cart/items', ['product_id' => $this->battery->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('cart.count', 2);
        $this->postJson('/cart/items', ['product_id' => $this->battery->id, 'quantity' => 5])
            ->assertOk()->assertJsonPath('cart.count', 3);

        $this->postJson('/cart/items', ['product_id' => $this->onRequest->id])->assertStatus(422);
    }

    public function test_checkout_places_order_and_reserves_stock(): void
    {
        $this->post('/cart/items', ['product_id' => $this->battery->id, 'quantity' => 2]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Nimal Perera', 'phone' => '077 123 4567',
            'fulfilment' => 'delivery', 'address' => '12 Main St', 'city' => 'Malabe',
            'payment_method' => 'cod',
        ]);

        $order = Order::with('items')->firstOrFail();
        $response->assertRedirect();
        $this->assertStringContainsString('/orders/'.$order->number, $response->headers->get('Location'));
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertEquals(25000, (float) $order->subtotal);
        $this->assertEquals(25000 + (float) $order->delivery_fee, (float) $order->total);
        $this->assertSame(1, $this->battery->fresh()->stock_qty);

        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->number);
        $this->get('/orders/'.$order->number)->assertForbidden(); // unsigned link
        $this->get('/cart')->assertSee('Your cart is empty');
    }

    public function test_checkout_rejects_when_stock_ran_out(): void
    {
        $this->post('/cart/items', ['product_id' => $this->display->id]);
        $this->display->update(['stock_qty' => 0]);

        $this->post('/checkout', [
            'customer_name' => 'A', 'phone' => '0771234567', 'fulfilment' => 'pickup', 'payment_method' => 'pay_at_store',
        ]);

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_requires_address(): void
    {
        $this->post('/cart/items', ['product_id' => $this->battery->id]);
        $this->post('/checkout', ['customer_name' => 'A', 'phone' => '0771234567', 'fulfilment' => 'delivery', 'payment_method' => 'cod'])
            ->assertSessionHasErrors(['address', 'city']);
    }

    public function test_repair_and_part_enquiries_are_stored(): void
    {
        $this->postJson('/enquiries', [
            'type' => 'repair', 'name' => 'Kamal', 'phone' => '0771234567',
            'device' => 'Dell Inspiron 15', 'service' => 'Motherboard repair', 'message' => 'No power',
        ])->assertCreated()->assertJsonStructure(['reference', 'whatsapp']);

        $this->postJson('/enquiries', [
            'type' => 'part', 'name' => 'Kamal', 'phone' => '0771234567',
            'details' => ['brand' => 'Dell', 'model' => 'Inspiron 15 3520', 'part' => 'Hinge'],
        ])->assertCreated();

        $this->assertSame(2, Enquiry::count());
        $this->assertSame('Hinge', Enquiry::where('type', 'part')->first()->details['part']);
    }

    public function test_enquiry_validation_and_honeypot(): void
    {
        $this->postJson('/enquiries', ['type' => 'repair', 'name' => 'X', 'phone' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['phone', 'device', 'message']);

        $this->postJson('/enquiries', ['type' => 'general', 'name' => 'Bot', 'phone' => '0771234567', 'website' => 'spam'])
            ->assertStatus(422);
    }
}
