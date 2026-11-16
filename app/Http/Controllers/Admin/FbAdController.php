<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Ai\FbAdMaker;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Facebook ad post maker for one product. The server returns the AI copy and
 * scene picture; the page lays them out at every Facebook ad size.
 */
class FbAdController extends Controller
{
    public function __construct(private FbAdMaker $ads) {}

    public function create(Product $product, GeminiClient $gemini): View
    {
        return view('admin.products.fb-ad', [
            'product' => $product,
            'configured' => $gemini->configured(),
            'productImage' => ($path = $this->ads->productImage($product)) ? asset($path) : null,
            'fonts' => $this->ads->fonts(),
            'contact' => $this->contactLine(),
        ]);
    }

    public function generate(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'idea' => ['nullable', 'string', 'max:1500'],
            'language' => ['required', Rule::in(array_keys(FbAdMaker::LANGUAGES))],
            'with_price' => ['nullable', 'boolean'],
            'parts' => ['required', Rule::in(['all', 'text', 'image'])],
            // Re-drawing only the picture reuses the copy already on the page.
            'scene' => ['nullable', 'string', 'max:600'],
            'bg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hl' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout' => ['nullable', Rule::in(array_keys(FbAdMaker::LAYOUTS))],
        ]);
        @set_time_limit(300);
        $idea = trim((string) ($data['idea'] ?? ''));

        $result = ['copy' => null, 'image' => null, 'warning' => null];

        try {
            if ($data['parts'] !== 'image') {
                $result['copy'] = $this->ads->copy($product, $idea, $data['language'], $request->boolean('with_price'));
            }
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($data['parts'] !== 'text') {
            $scene = $result['copy']['scene'] ?? (string) ($data['scene'] ?? '');
            $bg = $result['copy']['bg'] ?? ($data['bg'] ?? '#101318');
            $hl = $result['copy']['hl'] ?? ($data['hl'] ?? '#ffd400');
            try {
                // Path only: keeps the picture same-origin so the browser canvas can export it.
                $result['image'] = parse_url(asset($this->ads->scene($product, $scene, $bg, $idea, $data['layout'] ?? 'tech', $hl)), PHP_URL_PATH);
            } catch (Throwable $e) {
                $result['warning'] = 'AI picture not created: '.$e->getMessage().' The product photo is used instead.';
            }
        }

        return response()->json($result);
    }

    /** "SpeedTech | 0750-591279 | speedtech.lk" (the web address is left out on a local copy). */
    private function contactLine(): string
    {
        $host = (string) parse_url(config('app.url'), PHP_URL_HOST);
        $local = $host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || str_ends_with($host, '.test');

        return config('app.name', 'SpeedTech').'  |  '.config('speedtech.phone_display').($local ? '' : '  |  '.$host);
    }

    /** Upload a Sinhala Unicode font (TTF / OTF / WOFF) for the ad headlines. */
    public function uploadFont(Request $request): RedirectResponse
    {
        $request->validate(['font' => ['required', 'file', 'max:8192']]);
        $file = $request->file('font');
        $ext = Str::lower($file->getClientOriginalExtension());

        if (! in_array($ext, ['ttf', 'otf', 'woff', 'woff2'], true) || ! FbAdMaker::isFont((string) file_get_contents($file->getRealPath(), length: 4))) {
            return back()->with('error', 'That is not a font file. Upload a .ttf, .otf, .woff or .woff2 Sinhala Unicode font.');
        }

        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'font-'.Str::lower(Str::random(6));
        File::ensureDirectoryExists(public_path(FbAdMaker::FONT_DIR));
        $file->move(public_path(FbAdMaker::FONT_DIR), "{$name}.{$ext}");

        return back()->with('status', 'Font added. Pick it under “Sinhala font”.');
    }

    public function deleteFont(string $file): RedirectResponse
    {
        $match = collect($this->ads->fonts())->firstWhere('file', $file);
        abort_unless($match, 404);
        File::delete(public_path(FbAdMaker::FONT_DIR.'/'.$match['file']));

        return back()->with('status', 'Font removed.');
    }
}
