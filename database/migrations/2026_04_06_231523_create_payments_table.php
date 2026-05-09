<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');     //at2kd mn el nullable      
            $table->string('transaction_id')->nullable()->unique();
            $table->enum('payment_method',['card','cash']);
            $table->enum('payment_status', ['pending', 'completed', 'failed','refunded'])->default('pending');
            $table->decimal('amount',10,2);
            $table->json('payment_details')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
