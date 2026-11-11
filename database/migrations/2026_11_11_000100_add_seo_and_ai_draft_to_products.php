<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO fields for product pages, and a flag for products written by the AI
 * product writer that still need a human review before they go live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('description');
            $table->string('meta_description', 300)->nullable()->after('meta_title');
            $table->boolean('ai_draft')->default(false)->index()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['ai_draft']);
            $table->dropColumn(['meta_title', 'meta_description', 'ai_draft']);
        });
    }
};
