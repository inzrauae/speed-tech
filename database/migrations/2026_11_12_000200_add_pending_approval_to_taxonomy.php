<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brands, laptop models, years, part types and shop categories suggested by the
 * AI product writer start as "pending approval": hidden from the store until an
 * admin approves them on the Pending approvals page.
 */
return new class extends Migration
{
    private const TABLES = ['brands', 'laptop_models', 'model_generations', 'part_categories', 'categories'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('pending_approval')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['pending_approval']);
                $table->dropColumn('pending_approval');
            });
        }
    }
};
