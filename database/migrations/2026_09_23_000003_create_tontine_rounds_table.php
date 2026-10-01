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
        Schema::create('tontine_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained('tontines')->onDelete('cascade');
            $table->unsignedInteger('round_number');
            $table->string('beneficiary_phone');
            $table->dateTime('due_date');
            $table->decimal('total_expected_amount', 15, 2);
            $table->decimal('total_collected_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->enum('payout_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->dateTime('payout_at')->nullable();
            $table->timestamps();

            $table->unique(['tontine_id', 'round_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontine_rounds');
    }
};
