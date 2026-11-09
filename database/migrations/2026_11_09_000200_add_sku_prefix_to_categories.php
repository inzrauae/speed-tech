<?php

use App\Services\SkuGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short product-code prefix per shop category / spare-part type, e.g. LCD, KBD, RAM.
 * Product SKUs are generated as PREFIX-00001 (see App\Services\SkuGenerator).
 */
return new class extends Migration
{
    // Conventional abbreviations used in the computer parts trade.
    private const KNOWN = [
        'lcd-led-display' => 'LCD', 'keyboard' => 'KBD', 'battery' => 'BAT', 'speaker' => 'SPK',
        'cooling-fan' => 'FAN', 'motherboard' => 'MBD', 'hinge' => 'HNG', 'touchpad' => 'TPD',
        'dc-power-jack' => 'DCJ', 'display-cable' => 'LVD', 'palmrest-housing' => 'PLM', 'webcam' => 'CAM',
        'laptops' => 'LAP', 'pc-components' => 'CMP', 'storage' => 'STG', 'ram' => 'RAM',
        'accessories' => 'ACC', 'chargers-adapters' => 'ADP', 'cables' => 'CAB',
    ];

    public function up(): void
    {
        foreach (['part_categories', 'categories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('sku_prefix', 6)->nullable()->after('slug');
            });
        }

        $taken = [];
        foreach (['part_categories', 'categories'] as $table) {
            foreach (DB::table($table)->orderBy('id')->get(['id', 'slug', 'name']) as $row) {
                $prefix = self::KNOWN[$row->slug] ?? null;
                if (! $prefix || in_array($prefix, $taken, true)) {
                    $prefix = SkuGenerator::derivePrefix($row->name, $taken);
                }
                $taken[] = $prefix;
                DB::table($table)->where('id', $row->id)->update(['sku_prefix' => $prefix]);
            }
        }
    }

    public function down(): void
    {
        foreach (['part_categories', 'categories'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('sku_prefix'));
        }
    }
};
