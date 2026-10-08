<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('method_id')->constrained('withdrawal_methods');
            $table->unsignedBigInteger('coins_debited');
            // Money is stored as integers only — paise for INR, cents for USD.
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('tax_paise')->default(0);
            $table->unsignedBigInteger('net_paise');
            $table->unsignedBigInteger('net_usd_cents')->nullable();
            $table->enum('currency', ['INR', 'USD'])->default('INR');
            // Payout details: upi_id | mobile | account_no+ifsc+holder | paypal_email+name …
            $table->json('details');
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'processing', 'completed', 'failed',
            ])->default('pending');
            $table->text('admin_note')->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->string('payout_reference', 120)->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
