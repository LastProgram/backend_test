<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('referrer_master_id')->constrained('masters')->cascadeOnDelete();
            $table->foreignId('referred_master_id')->constrained('masters')->cascadeOnDelete();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedInteger('payment_amount');
            $table->bigInteger('amount');
            $table->integer('percent');
            $table->string('status')->default('pending');

            $table->unique('referral_id');
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};
