<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('order_status', ['pending', 'processing', 'in_transit', 'delivered', 'completed','cancelled'])->default('pending');
            $table->enum('delivery_type', ['pickup', 'delivery']);
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_fees', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['card', 'cash']);
            $table->timestamp('order_date')->useCurrent();
            $table->timestamps(); //ya aktfy b de ya ashelha we ahot el order date
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
