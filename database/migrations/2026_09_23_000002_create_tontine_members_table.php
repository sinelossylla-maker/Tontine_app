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
        Schema::create('tontine_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained('tontines')->onDelete('cascade');
            $table->string('member_phone');
            $table->unsignedInteger('turn_order');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Uniqueness constraints
            $table->unique(['tontine_id', 'member_phone']);
            $table->unique(['tontine_id', 'turn_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontine_members');
    }
};
