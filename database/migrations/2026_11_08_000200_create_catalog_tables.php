<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shop categories for universal accessories (Laptops, Storage, RAM, ...).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Laptop brands (Dell, HP, Lenovo, ...).
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Laptop models (Inspiron 15 3520, Pavilion 15, ...).
        Schema::create('laptop_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['brand_id', 'slug']);
        });

        // Manufacturing year / generation of a model (2022–2024, 11th Gen, ...).
        Schema::create('model_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laptop_model_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Spare part item types (LCD display, keyboard, battery, ...).
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('accessory')->index(); // accessory | spare_part
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('oem_part_number')->nullable()->index();
            $table->string('tag')->nullable();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('specification')->nullable();   // 15.6", FHD, 30-pin
            $table->string('connector_type')->nullable();  // 30-pin eDP
            $table->string('size')->nullable();            // 15.6"
            $table->decimal('price', 12, 2)->nullable();   // null = price on request
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_qty')->default(0);
            $table->string('warranty')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // A part can fit several models / generations.
        Schema::create('model_generation_product', function (Blueprint $table) {
            $table->foreignId('model_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['model_generation_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_generation_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('part_categories');
        Schema::dropIfExists('model_generations');
        Schema::dropIfExists('laptop_models');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
