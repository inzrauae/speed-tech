<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\ManufacturerPhotoFinder;
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
        // No real DNS in tests: every host is "public" unless it is written as an IP.
        ManufacturerPhotoFinder::$resolveUsing = fn (string $host) => filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ['93.184.216.34'];
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        ManufacturerPhotoFinder::$resolveUsing = null;
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
            'image_search' => 'Dell Inspiron 15 3520 keyboard 0KN4DH',
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

    /**
     * Fake Gemini (writer, Google Search, photo check) and the manufacturer's website.
     *
     * @param  list<string>  $found  URLs the search answers with
     */
    private function fakeGemini(array $draft, array $found = ['https://www.dell.com/kb-3520'], int $best = 0): void
    {
        // A product photo on a white background, like manufacturers publish.
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 200, 200, 600, 400, imagecolorallocate($img, 20, 20, 20));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $answer = fn ($text) => Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]);

        Http::fake([
            '*/text-model:generateContent' => fn ($request) => match (true) {
                isset($request->data()['tools']) => $answer(implode("\n", $found)),
                str_contains(json_encode($request->data()), 'best shop photo') => $answer(json_encode(['best' => $best, 'reason' => 'wrong connector'])),
                default => $answer(json_encode($draft)),
            },
            '*/image-model:generateContent' => Http::response(['error' => ['message' => 'must not be called']], 500),
            'www.dell.com/kb-3520' => Http::response('<html><head><meta property="og:image" content="/img/kb-3520.png"></head><body></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'www.dell.com/img/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('not found', 404),
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
            'title' => 'dell 3520 keyboard backlit', 'image_mode' => 'web',
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

        // The manufacturer's photo is searched for with the writer's exact words; nothing is drawn.
        Http::assertSent(fn ($request) => isset($request->data()['tools']) && str_contains(json_encode($request->data()), '0KN4DH'));
        Http::assertSent(fn ($request) => $request->url() === 'https://www.dell.com/img/kb-3520.png');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image-model'));
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

    public function test_own_photo_is_used_when_no_manufacturer_photo_is_found(): void
    {
        Category::create(['name' => 'Mice', 'is_active' => true]);
        $this->fakeGemini($this->draft(['type' => 'accessory', 'category' => 'Mice', 'compatible' => []]), found: []);

        $this->post('/admin/products/ai-writer', [
            'title' => 'wireless mouse', 'image_mode' => 'web',
            'photo' => UploadedFile::fake()->image('mouse.jpg', 800, 600),
        ])->assertSessionHas('ai_report', fn ($report) => str_contains($report['warnings'][0], 'No manufacturer photo'));

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
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'web']);
        $product = Product::sole();
        $old = $product->image;

        $this->post(route('admin.ai.image', $product))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'www.dell.com'));

        $this->assertNotSame($old, $product->refresh()->image);
        $this->assertFileDoesNotExist(public_path($old));
        $this->assertFileExists(public_path($product->image));
    }

    public function test_photo_search_uses_the_products_connector_and_specification(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();
        $product->forceFill(['specification' => '15.6" FHD', 'connector_type' => '30-pin eDP', 'size' => '15.6 inch', 'oem_part_number' => 'NV156FHM-N61'])->save();

        $this->post(route('admin.ai.image', $product))->assertSessionHas('status');

        $text = fn ($request) => json_encode($request->data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Http::assertSent(fn ($request) => isset($request->data()['tools'])
            && str_contains($text($request), 'Connector: 30-pin eDP')
            && str_contains($text($request), 'Size: 15.6 inch')
            && str_contains($text($request), 'NV156FHM-N61'));
        // The photo check gets the specification too, so a look-alike model is rejected.
        Http::assertSent(fn ($request) => str_contains($text($request), 'best shop photo') && str_contains($text($request), '30-pin eDP'));
    }

    public function test_a_pasted_link_is_used_without_searching(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();

        $this->post(route('admin.ai.image', $product), ['image_url' => 'https://www.dell.com/kb-3520'])->assertSessionHas('status');

        $this->assertFileExists(public_path($product->refresh()->image));
        $this->assertSame([1200, 1200], array_slice(getimagesize(public_path($product->image)), 0, 2));
        Http::assertNotSent(fn ($request) => isset($request->data()['tools']));
    }

    public function test_a_photo_the_ai_says_is_the_wrong_model_is_not_used(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]), found: ['https://www.dell.com/kb-3520', 'https://www.dell.com/img/other.png'], best: -1);
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();

        $this->post(route('admin.ai.image', $product))->assertSessionHas('error', fn ($e) => str_contains($e, 'none clearly shows this exact product'));
        $this->assertNull($product->refresh()->image);
    }

    public function test_searched_photos_from_shops_are_never_used(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]), found: ['https://www.some-laptop-shop.lk/img/kb-3520.png']);
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();

        $this->post(route('admin.ai.image', $product))->assertSessionHas('error', fn ($e) => str_contains($e, 'official Dell website'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'some-laptop-shop.lk'));
        $this->assertNull($product->refresh()->image);
    }

    public function test_the_official_domain_named_by_the_search_is_accepted(): void
    {
        // The brand alone does not match the host, so only the OFFICIAL line lets this photo in.
        $this->fakeGemini($this->draft(['compatible' => []]), found: ['OFFICIAL: dell.com', 'https://www.dell.com/img/kb-3520.png']);
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();
        $product->forceFill(['brand' => 'Acme'])->save();

        $this->post(route('admin.ai.image', $product))->assertSessionHas('status', fn ($s) => str_contains($s, 'www.dell.com'));
    }

    public function test_image_layout_product_at_70_percent_with_logo_bottom_left(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'web']);
        $img = imagecreatefromjpeg(public_path(Product::sole()->image));

        // The 400×200 dark product fills 70% of the width: 840 px, centred.
        $dark = fn ($x, $y) => (imagecolorat($img, $x, $y) & 0xFF) < 80;
        $this->assertTrue($dark(600, 600));
        $this->assertTrue($dark(215, 600) && $dark(985, 600));
        $this->assertFalse($dark(170, 600) || $dark(1030, 600));

        // The logo (red) is in the bottom-left corner, not the bottom-right.
        $red = function ($x0, $x1) use ($img) {
            for ($x = $x0; $x < $x1; $x += 2) {
                for ($y = 1080; $y < 1170; $y += 2) {
                    $c = imagecolorat($img, $x, $y);
                    if (($c >> 16 & 255) > 180 && ($c >> 8 & 255) < 90) {
                        return true;
                    }
                }
            }

            return false;
        };
        $this->assertTrue($red(30, 360));
        $this->assertFalse($red(840, 1170));
    }

    public function test_own_photo_is_retouched_onto_a_white_background(): void
    {
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 205, 195, 175)); // dull, warm backdrop
        imagefilledrectangle($img, 250, 200, 550, 400, imagecolorallocate($img, 30, 30, 30));
        ob_start();
        imagejpeg($img, null, 95);
        $photo = UploadedFile::fake()->createWithContent('kb.jpg', ob_get_clean());

        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'photo', 'photo' => $photo]);
        $out = imagecreatefromjpeg(public_path(Product::sole()->image));

        // Backdrop next to the product is now white and neutral.
        $c = imagecolorsforindex($out, imagecolorat($out, 600, 170));
        $this->assertGreaterThanOrEqual(245, min($c['red'], $c['green'], $c['blue']));
    }

    public function test_links_to_private_addresses_are_never_fetched(): void
    {
        $this->fakeGemini($this->draft(['compatible' => []]));
        $this->post('/admin/products/ai-writer', ['title' => 'keyboard', 'image_mode' => 'none']);
        $product = Product::sole();

        foreach (['http://127.0.0.1/admin.png', 'http://192.168.1.1/photo.jpg', 'http://169.254.169.254/latest/meta-data'] as $url) {
            $this->post(route('admin.ai.image', $product), ['image_url' => $url])->assertSessionHas('error');
        }

        Http::assertNotSent(fn ($request) => preg_match('#//(127\.|192\.168\.|169\.254\.)#', $request->url()));
        $this->assertNull($product->refresh()->image);
    }
}
