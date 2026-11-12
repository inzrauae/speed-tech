<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra SEO content for product pages: the manufacturer brand (structured data),
 * the main search keyword the copy targets, image alt text and an FAQ list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand', 60)->nullable()->after('name');
            $table->string('focus_keyword', 100)->nullable()->after('meta_description');
            $table->string('image_alt', 200)->nullable()->after('image');
            $table->json('faqs')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'focus_keyword', 'image_alt', 'faqs']);
        });
    }
};
