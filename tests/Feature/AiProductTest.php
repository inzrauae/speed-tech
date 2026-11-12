<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key', 'services.gemini.text_model' => 'text-model', 'services.gemini.image_model' => 'image-model']);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        foreach (Product::whereNotNull('image')->pluck('image') as $path) {
            File::delete(public_path($path));
        }
        parent::tearDown();
    }

    private function draft(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'spare_part',
            'name' => 'Dell Inspiron 15 3520 Backlit Keyboard US',
            'summary' => 'Backlit US keyboard for the Inspiron 15 3520.',
            'description' => "Replacement keyboard.\n\n• Backlit",
            'meta_title' => 'Dell Inspiron 15 3520 Keyboard | SpeedTech',
            'meta_description' => 'Buy a Dell Inspiron 15 3520 backlit keyboard in Sri Lanka.',
            'specification' => 'US layout, backlit',
            'size' => '', 'connector_type' => '', 'tag' => 'oem-grade',
            'category' => 'Keyboard',
            'brand' => 'Dell',
            'compatible' => [['brand' => 'Dell', 'model' => 'Inspiron 15 3520', 'years' => '2022-2023']],
            'image_prompt' => 'A black laptop keyboard',
            'review_notes' => 'Check the ribbon cable.',
            'focus_keyword' => 'Dell Inspiron 15 3520 keyboard',
            'image_alt' => 'Black backlit US keyboard for the Dell Inspiron 15 3520 laptop',
            'faqs' => [['q' => 'Will it fit my 3520?', 'a' => 'Yes, match the part number first.'], ['q' => '', 'a' => 'dropped']],
        ];
    }

    public function test_new_taxonomy_from_the_ai_waits_for_approval_and_is_hidden(): void
    {
        $this->fakeGemini($this->draft());
        $this->post('/admin/products/ai-writer', ['title' => 'dell 3520 keyboard', 'image_mode' => 'none']);

        $brand = Brand::where('name', 'Dell')->sole();
        $model = LaptopModel::where('name', 'Inspiron 15 3520')->sole();
        $year = $model->generations()->sole();
        $part = PartCategory::where('name', 'Keyboard')->sole();
        foreach ([$brand, $model, $part] as $item) {
            $this->assertTrue($item->pending_approval);
            $this->assertFalse($item->is_active);
        }
        $this->assertTrue($year->pending_approval);

        // Hidden from the public finder.
        $this->getJson('/api/finder/brands')->assertJsonMissing(['name' => 'Dell']);
        $this->getJson('/api/finder/generations?model='.$model->id)->assertExactJson([]);

        $this->get(route('admin.approvals.index'))->assertOk()
            ->assertSee('New laptop brands')->assertSee('New laptop models')->assertSee('New years / generations')->assertSee('New part types');

        // Approving the year (renamed) also approves its model and brand.
        $this->post(route('admin.approvals.approve', ['years', $year->id]), ['label' => '2022–2024'])->assertSessionHasNoErrors();
        $this->assertSame('2022–2024', $year->refresh()->label);
        $this->assertFalse($year->pending_approval);
        $this->assertTrue($model->refresh()->is_active && ! $model->pending_approval);
        $this->assertTrue($brand->refresh()->is_active && ! $brand->pending_approval);
        $this->getJson('/api/finder/generations?model='.$model->id)->assertJsonFragment(['label' => '2022–2024']);
    }

    public function test_a_suggestion_can_be_replaced_with_an_existing_entry_or_rejected(): void
    {
        $existing = PartCategory::create(['name' => 'Keyboards', 'is_active' => true]);
        $this->fakeGemini($this->draft(['category' => 'Laptop Keyboard', 'compatible' => [['brand' => 'Dull', 'model' => 'X1', 'years' => '']]]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();
        $suggested = PartCategory::where('name', 'Laptop Keyboard')->sole();

        $this->post(route('admin.approvals.replace', ['part-types', $suggested->id]), ['replace_with' => $existing->id])->assertSessionHasNoErrors();
        $this->assertModelMissing($suggested);
        $this->assertSame($existing->id, $product->refresh()->part_category_id);

        $brand = Brand::where('name', 'Dull')->sole();
        $this->delete(route('admin.approvals.reject', ['brands', $brand->id]))->assertSessionHasNoErrors();
        $this->assertModelMissing($brand);
        $this->assertSame(0, LaptopModel::where('name', 'X1')->count());
        $this->assertCount(0, $product->generations()->get());
        $this->assertSame(0, \App\Http\Controllers\Admin\ApprovalController::pendingCount());
    }

    public function test_draft_gets_seo_fields_and_the_page_outputs_structured_data(): void
    {
        $this->fakeGemini($this->draft(['description' => "Intro text.\n\n## Key features\n\n• Backlit\n• US layout", 'meta_title' => 'Dell 3520 Keyboard | SpeedTech']));
        $this->post('/admin/products/ai-writer', ['title' => 'dell 3520 keyboard', 'image_mode' => 'none']);

        $product = Product::sole();
        $this->assertSame('Dell', $product->brand);
        $this->assertSame('Dell Inspiron 15 3520 keyboard', $product->focus_keyword);
        $this->assertSame('Dell 3520 Keyboard', $product->meta_title);
        $this->assertSame([['q' => 'Will it fit my 3520?', 'a' => 'Yes, match the part number first.']], $product->faqs);

        $product->forceFill(['is_active' => true, 'price' => 12500, 'stock_qty' => 2])->save();
        $html = $this->get($product->url())->assertOk()
            ->assertSee('<link rel="canonical" href="'.$product->url().'">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<h3>Key features</h3>', false)
            ->assertSee('<li>Backlit</li>', false)
            ->assertSee('Will it fit my 3520?')
            ->assertSee('alt="Black backlit US keyboard', false)
            ->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $graphs = collect($m[1])->map(fn ($j) => json_decode($j, true))->keyBy('@type');
        $this->assertSame(['Product', 'BreadcrumbList', 'FAQPage'], $graphs->keys()->all());
        $this->assertSame('12500.00', $graphs['Product']['offers']['price']);
        $this->assertSame('LKR', $graphs['Product']['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $graphs['Product']['offers']['availability']);
        $this->assertSame('Dell', $graphs['Product']['brand']['name']);
        $this->assertSame('Dell Inspiron 15 3520', $graphs['Product']['isAccessoryOrSparePartFor'][0]['name']);
        $this->assertCount(4, $graphs['BreadcrumbList']['itemListElement']);
    }

    private function fakeGemini(array $draft, bool $imageWorks = true): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(64, 64));
        $png = base64_encode(ob_get_clean());

        Http::fake([
            '*/text-model:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($draft)]]]]]]),
            '*/image-model:generateContent' => $imageWorks
                ? Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]]])
                : Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded, limit: 0']], 429),
        ]);
    }

    public function test_ai_writer_page_renders(): void
    {
        $this->get('/admin/products/ai-writer')->assertOk()->assertSee('Write product draft');
    }

    public function test_title_becomes_a_hidden_draft_and_missing_taxonomy_is_created(): void
    {
        $this->fakeGemini($this->draft());

        $response = $this->post('/admin/products/ai-writer', [
            'title' => 'dell 3520 keyboard backlit', 'image_mode' => 'ai', 'image_details' => 'top view',
        ]);

        $product = Product::sole();
        $response->assertRedirect(route('admin.products.edit', $product))->assertSessionHas('ai_report');
        $this->assertFalse($product->is_active);
        $this->assertTrue($product->ai_draft);
        $this->assertSame('OEM-GRADE', $product->tag);
        $this->assertSame('Keyboard', $product->partCategory->name);
        $this->assertNotNull(Brand::where('name', 'Dell')->first());
        $model = LaptopModel::where('name', 'Inspiron 15 3520')->sole();
        $this->assertSame([2022, 2023], [$model->generations->first()->year_from, $model->generations->first()->year_to]);
        $this->assertSame($model->generations->pluck('id')->all(), $product->generations->pluck('id')->all());
        $this->assertStringStartsWith('uploads/products/', $product->image);
        $this->assertSame([1200, 1200], array_slice(getimagesize(public_path($product->image)), 0, 2));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'image-model')
            && str_contains(json_encode($request->data()), 'top view'));
    }

    public function test_existing_laptop_years_are_reused(): void
    {
        $model = LaptopModel::create(['brand_id' => Brand::create(['name' => 'Dell'])->id, 'name' => 'Inspiron 15 3520']);
        $year = $model->generations()->create(['label' => '2022–2024', 'year_from' => 2022, 'year_to' => 2024]);

        foreach (['', '2022-2023'] as $years) {
            $this->fakeGemini($this->draft(['compatible' => [['brand' => 'dell', 'model' => 'Inspiron 15 3520', 'years' => $years]]]));
            $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
            $this->assertSame([$year->id], Product::latest('id')->first()->generations->pluck('id')->all(), "years: '{$years}'");
        }
        $this->assertSame(1, $model->generations()->count());
        $this->assertSame(1, Brand::count());
    }

    public function test_existing_category_is_reused(): void
    {
        $existing = PartCategory::create(['name' => 'Keyboards', 'is_active' => true]);
        $this->fakeGemini($this->draft(['compatible' => []]));

        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);

        $this->assertSame(1, PartCategory::count());
        $this->assertSame($existing->id, Product::sole()->part_category_id);
    }

    public function test_photo_is_used_with_logo_when_ai_image_is_not_available(): void
    {
        Category::create(['name' => 'Mice', 'is_active' => true]);
        $this->fakeGemini($this->draft(['type' => 'accessory', 'category' => 'Mice', 'compatible' => []]), imageWorks: false);

        $this->post('/admin/products/ai-writer', [
            'title' => 'wireless mouse', 'image_mode' => 'ai',
            'photo' => UploadedFile::fake()->image('mouse.jpg', 800, 600),
        ])->assertSessionHas('ai_report', fn ($report) => str_contains($report['warnings'][0], 'billing'));

        $product = Product::sole();
        $this->assertSame(Product::TYPE_ACCESSORY, $product->type);
        $this->assertSame([], $product->generations->all());
        $this->assertFileExists(public_path($product->image));
    }

    public function test_ai_failure_keeps_the_form_input(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 403)]);

        $this->from('/admin/products/ai-writer')->post('/admin/products/ai-writer', ['title' => 'mouse', 'image_mode' => 'none'])
            ->assertRedirect('/admin/products/ai-writer')
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'API key'))
            ->assertSessionHasInput('title', 'mouse');
        $this->assertSame(0, Product::count());
    }

    public function test_publishing_a_draft_clears_the_flag_and_uses_seo_fields(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();

        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('AI draft')->assertSee('Rewrite with AI')->assertSee('(pending approval)');

        $publish = [
            'type' => 'spare_part', 'name' => $product->name, 'part_category_id' => $product->part_category_id,
            'stock_qty' => 2, 'is_active' => 1, 'meta_title' => 'Custom title', 'meta_description' => 'Custom meta description',
        ];
        // The AI-suggested part type must be approved before the product can go live.
        $this->put(route('admin.products.update', $product), $publish)->assertSessionHasErrors('is_active');
        $this->assertFalse($product->refresh()->is_active);

        $this->post(route('admin.approvals.approve', ['part-types', $product->part_category_id]), ['name' => 'Keyboards'])->assertSessionHasNoErrors();
        $this->put(route('admin.products.update', $product), $publish)->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertFalse($product->ai_draft);
        $this->get($product->url())->assertSee('<title>Custom title |', false)->assertSee('content="Custom meta description"', false);
    }

    public function test_regenerating_an_image_replaces_the_old_upload(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'ai']);
        $product = Product::sole();
        $old = $product->image;

        $this->post(route('admin.ai.image', $product), ['image_details' => 'red background', 'use_current' => 1])
            ->assertSessionHas('status');

        $this->assertNotSame($old, $product->refresh()->image);
        $this->assertFileDoesNotExist(public_path($old));
        $this->assertFileExists(public_path($product->image));
    }
}
