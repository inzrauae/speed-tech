<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Inventory
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reorder_level')->default(3)->after('stock_qty');
            $table->decimal('cost_price', 12, 2)->nullable()->after('compare_at_price');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();      // opening | received | sale | returned | adjustment | damaged
            $table->integer('quantity');              // + in, - out
            $table->unsignedInteger('balance_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference')->nullable();  // order number, supplier invoice…
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Opening balance for stock that existed before inventory tracking.
        $now = now();
        DB::table('products')->where('stock_qty', '>', 0)->orderBy('id')->each(function ($p) use ($now) {
            DB::table('stock_movements')->insert([
                'product_id' => $p->id, 'type' => 'opening', 'quantity' => $p->stock_qty,
                'balance_after' => $p->stock_qty, 'note' => 'Opening balance', 'created_at' => $now,
            ]);
        });

        // ---- Payments
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('payment_method')->index(); // unpaid | paid | refunded
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->string('payment_slip')->nullable()->after('payment_reference');
        });

        // ---- Shop settings editable in the admin (payments, bank details, delivery)
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['payment_status', 'paid_at', 'payment_reference', 'payment_slip']));
        Schema::dropIfExists('stock_movements');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['reorder_level', 'cost_price']));
    }
};
