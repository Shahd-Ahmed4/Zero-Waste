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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->unique()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('admins');
            $table->string('business_name')->nullable();
            $table->string('logo')->nullable(); // مسار صورة اللوجو
            $table->string('vendor_type')->nullable();  
            // $table->string('opening_hours')->nullable();
            // $table->string('store_address')->nullable();
            // $table->string('contact_email')->nullable();
            // $table->string('contact_phone')->nullable();
            // $table->decimal('lat', 10, 8)->nullable();
            // $table->decimal('long', 11, 8)->nullable();
            $table->text('rejection_reason')->nullable();
        // الأوراق القانونية
            $table->string('tax_number')->nullable();
            $table->string('commercial_register')->nullable();
            $table->string('tax_card')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
