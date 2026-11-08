<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('customer_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('fulfilment', 20);      // pickup | delivery
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('payment_method', 30);  // cod | bank_transfer | pay_at_store
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('oem_part_number')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type', 20)->index();   // product | part | repair | general
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('device')->nullable();
            $table->string('service')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();   // finder selection, etc.
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new')->index(); // new | contacted | closed
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
