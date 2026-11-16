<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Ai\FbAdMaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FbAdTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key', 'services.gemini.text_model' => 'text-model', 'services.gemini.image_model' => 'image-model']);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $this->product = Product::create([
            'type' => 'accessory', 'name' => 'Logitech M331 Silent Mouse', 'summary' => 'Quiet wireless mouse.',
            'price' => 4500, 'compare_at_price' => 5200, 'stock_qty' => 5, 'reorder_level' => 1, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete(File::glob(public_path(FbAdMaker::DIR.'/ad-'.$this->product->id.'-*.jpg')));
        parent::tearDown();
    }

    private function fakeGemini(bool $imageWorks = true): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(64, 64));
        $png = base64_encode(ob_get_clean());

        $copy = [
            'headline' => 'ශබ්ද නැති Click එකක්', 'subline' => 'ඔයාගේ වැඩේට හරියටම ගැලපෙන Mouse එක',
            'offer' => 'අලුත්ම Stock', 'hero' => 'නොමිලේ!', 'price_note' => 'විතරයි',
            'hook' => 'සුපිරිම Offer එකක්!', 'freebie' => 'මාස 6ක Warranty + Free Bag',
            'cards' => [['title' => 'Core Specs', 'points' => ['Silent', '2.4GHz', 'USB', 'extra']], ['title' => 'ඇයි අපෙන්?', 'points' => ['COD']], ['title' => 'third', 'points' => []]],
            'features' => [['icon' => 'mouse', 'text' => 'Silent clicks'], ['icon' => 'made-up', 'text' => 'Battery අවුරුදු 2ක්'], ['icon' => 'truck', 'text' => 'Delivery'], ['icon' => 'gift', 'text' => 'too many']],
            'cta' => 'WhatsApp කරන්න', 'caption' => "අලුත් Mouse එක ආවා 🖱️\n#SpeedTech",
            'scene' => 'dark desk with soft blue light', 'bg_color' => '0B1A2E', 'accent_color' => 'not-a-colour',
            'highlight_color' => '#22E1FF', 'style' => 'fire',
        ];

        Http::fake([
            '*/text-model:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($copy)]]]]]]),
            '*/image-model:generateContent' => $imageWorks
                ? Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]]])
                : Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded, limit: 0']], 429),
        ]);
    }

    public function test_page_renders_with_all_sizes_and_link_from_product_form(): void
    {
        $this->get(route('admin.products.edit', $this->product))->assertOk()->assertSee(route('admin.fb-ad.create', $this->product));

        $this->get(route('admin.fb-ad.create', $this->product))->assertOk()
            ->assertSee('Create ad with AI')->assertSee('Noto+Sans+Sinhala', false)
            ->assertSee('1080 × 1920')->assertSee('1200 × 628')->assertSee('Rs. 4,500');
    }

    public function test_ai_writes_sinhala_copy_and_draws_a_scene(): void
    {
        $this->fakeGemini();

        $response = $this->postJson(route('admin.fb-ad.generate', $this->product), [
            'idea' => 'අලුත් stock, island-wide delivery', 'language' => 'si', 'with_price' => 1, 'parts' => 'all',
        ])->assertOk();

        $copy = $response->json('copy');
        $this->assertSame('ශබ්ද නැති Click එකක්', $copy['headline']);
        $this->assertSame('නොමිලේ!', $copy['hero']);
        $this->assertSame('විතරයි', $copy['price_note']);
        $this->assertCount(3, $copy['features']);
        $this->assertSame(['mouse', 'check', 'truck'], array_column($copy['features'], 'icon'));
        $this->assertSame('#0b1a2e', $copy['bg']);
        $this->assertSame('#ed171f', $copy['accent']);
        $this->assertSame('#22e1ff', $copy['hl']);
        $this->assertSame('fire', $copy['style']);
        $this->assertSame('Rs. 4,500', $copy['price']);
        $this->assertSame('Rs. 5,200', $copy['was']);

        $image = $response->json('image');
        $this->assertStringStartsWith('/', $image);
        $this->assertStringContainsString('uploads/ads/ad-'.$this->product->id.'-', $image);
        $this->assertFileExists(public_path(ltrim(str_replace(parse_url(url('/'), PHP_URL_PATH) ?? '', '', $image), '/')));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'text-model') && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'SPOKEN Sinhala'));
        $this->assertSame('සුපිරිම Offer එකක්!', $copy['hook']);
        $this->assertCount(2, $copy['cards']);
        $this->assertSame(['Silent', '2.4GHz', 'USB'], $copy['cards'][0]['points']);

        // The default tech promo layout asks for a square hero shot on a glass pedestal, without text.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'image-model') && str_contains(json_encode($r->data()), 'no text')
            && $r['generationConfig']['imageConfig']['aspectRatio'] === '1:1' && str_contains(json_encode($r->data()), 'reflective dark glass pedestal'));
    }

    public function test_poster_layout_asks_for_a_wide_scene_with_a_glowing_ring(): void
    {
        $this->fakeGemini();
        $this->postJson(route('admin.fb-ad.generate', $this->product), ['language' => 'si', 'parts' => 'all', 'layout' => 'poster'])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'image-model')
            && $r['generationConfig']['imageConfig']['aspectRatio'] === '16:9' && str_contains(json_encode($r->data()), '#22e1ff neon light ring'));
    }

    public function test_text_only_skips_the_picture_and_price_can_be_left_out(): void
    {
        $this->fakeGemini();

        $this->postJson(route('admin.fb-ad.generate', $this->product), ['language' => 'en', 'parts' => 'text'])
            ->assertOk()->assertJsonPath('image', null)->assertJsonPath('copy.price', null);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'image-model'));
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'Do not mention a price'));
    }

    public function test_picture_failure_returns_copy_with_a_warning(): void
    {
        $this->fakeGemini(imageWorks: false);

        $this->postJson(route('admin.fb-ad.generate', $this->product), ['language' => 'mix', 'parts' => 'all'])
            ->assertOk()->assertJsonPath('image', null)->assertJsonPath('copy.headline', 'ශබ්ද නැති Click එකක්')
            ->assertJson(fn ($json) => $json->where('warning', fn ($w) => str_contains($w, 'AI picture not created'))->etc());
    }

    public function test_sinhala_unicode_fonts_can_be_uploaded_listed_and_removed(): void
    {
        $font = UploadedFile::fake()->createWithContent('UN-Ganganee.ttf', "\x00\x01\x00\x00".str_repeat("\0", 60));
        $this->post(route('admin.fb-ad.font'), ['font' => $font])->assertSessionHas('status');

        $this->assertFileExists(public_path(FbAdMaker::FONT_DIR.'/un-ganganee.ttf'));
        $this->get(route('admin.fb-ad.create', $this->product))->assertSee('Un Ganganee')->assertSee('uploads\/fonts\/un-ganganee.ttf', false);

        $this->delete(route('admin.fb-ad.font.delete', 'un-ganganee.ttf'))->assertSessionHas('status');
        $this->assertFileDoesNotExist(public_path(FbAdMaker::FONT_DIR.'/un-ganganee.ttf'));
    }

    public function test_a_script_renamed_to_a_font_is_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('evil.ttf', '<?php echo 1;');
        $this->post(route('admin.fb-ad.font'), ['font' => $fake])->assertSessionHas('error');
        $this->assertFileDoesNotExist(public_path(FbAdMaker::FONT_DIR.'/evil.ttf'));

        $this->delete(route('admin.fb-ad.font.delete', '..env'))->assertNotFound();
    }

    public function test_language_is_validated(): void
    {
        $this->postJson(route('admin.fb-ad.generate', $this->product), ['language' => 'ta', 'parts' => 'all'])->assertStatus(422);
    }
}
