<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->id();
            // upi | paytm | bank | cashfree | razorpay | payu | paypal_manual
            $table->string('type', 30)->unique();
            $table->string('name', 80);
            $table->boolean('enabled')->default(true);
            // Smallest currency unit (paise for INR, cents for USD).
            $table->unsignedBigInteger('min_amount');
            $table->unsignedBigInteger('max_amount');
            $table->enum('currency', ['INR', 'USD'])->default('INR');
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_methods');
    }
};
