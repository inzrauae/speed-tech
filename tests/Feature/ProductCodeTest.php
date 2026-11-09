<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\SkuGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_get_sequential_codes_per_category(): void
    {
        $lcd = PartCategory::create(['name' => 'LCD Display', 'sku_prefix' => 'LCD']);
        $ram = Category::create(['name' => 'RAM', 'sku_prefix' => 'RAM']);

        $a = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel A']);
        $b = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel B']);
        $c = Product::create(['type' => 'accessory', 'category_id' => $ram->id, 'name' => '8GB DDR4']);

        $this->assertSame('LCD-00001', $a->sku);
        $this->assertSame('LCD-00002', $b->sku);
        $this->assertSame('RAM-00001', $c->sku);
    }

    public function test_numbers_are_not_reused_and_codes_do_not_change_on_edit(): void
    {
        $lcd = PartCategory::create(['name' => 'LCD Display', 'sku_prefix' => 'LCD']);
        $a = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel A']);
        $b = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel B']);

        $a->update(['name' => 'Panel A (renamed)']);
        $this->assertSame('LCD-00001', $a->fresh()->sku);

        $a->delete();
        $c = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel C']);
        $this->assertSame('LCD-00003', $c->sku); // continues after the highest code
        $this->assertSame('LCD-00002', $b->fresh()->sku);
    }

    public function test_a_typed_code_is_kept(): void
    {
        $p = Product::create(['type' => 'accessory', 'name' => 'Mouse', 'sku' => ' my-code-1 ']);

        $this->assertSame('MY-CODE-1', $p->sku);
    }

    public function test_new_categories_get_a_unique_prefix(): void
    {
        $speaker = PartCategory::create(['name' => 'Speaker']);
        $other = Category::create(['name' => 'Speakers']);

        $this->assertSame('SPK', $speaker->sku_prefix);
        $this->assertNotSame('SPK', $other->sku_prefix);
        $this->assertSame('MNT', SkuGenerator::derivePrefix('Monitor'));
    }

    public function test_admin_form_generates_code_when_left_blank(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $battery = PartCategory::create(['name' => 'Battery', 'sku_prefix' => 'BAT']);

        $this->actingAs($admin)->post('/admin/products', [
            'type' => 'spare_part', 'name' => 'Dell 41Wh battery', 'part_category_id' => $battery->id,
            'oem_part_number' => 'WDX0R', 'sku' => '', 'stock_qty' => 0, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('BAT-00001', Product::where('name', 'Dell 41Wh battery')->value('sku'));
    }

    public function test_admin_cannot_reuse_a_category_code(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        PartCategory::create(['name' => 'LCD Display', 'sku_prefix' => 'LCD']);

        $this->actingAs($admin)->post('/admin/catalog/categories', ['name' => 'Monitors', 'sku_prefix' => 'lcd'])
            ->assertSessionHasErrors('sku_prefix');
    }

    public function test_command_fills_missing_codes(): void
    {
        $lcd = PartCategory::create(['name' => 'LCD Display', 'sku_prefix' => 'LCD']);
        $p = Product::create(['type' => 'spare_part', 'part_category_id' => $lcd->id, 'name' => 'Panel']);
        Product::whereKey($p->id)->update(['sku' => null]);

        $this->artisan('products:codes')->assertSuccessful();

        $this->assertSame('LCD-00001', $p->fresh()->sku);
    }
}
