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
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            // بيربط مع الـ id بتاع جدول الـ customers
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            // بيربط مع الـ id بتاع جدول الـ vendors
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->timestamps();

            // يمنع إن الزبون يضيف نفس الفيندور مرتين في المفضلة
            $table->unique(['customer_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
