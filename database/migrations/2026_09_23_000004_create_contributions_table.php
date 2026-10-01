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
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_round_id')->constrained('tontine_rounds')->onDelete('cascade');
            $table->string('member_phone');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_provider', ['wave', 'orange_money', 'mtn_momo']);
            $table->string('transaction_reference')->unique();
            $table->dateTime('payment_timestamp');
            $table->enum('punctuality_status', ['on_time', 'late']);
            $table->enum('payment_status', ['successful', 'failed', 'pending'])->default('successful');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
