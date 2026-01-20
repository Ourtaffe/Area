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
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->foreignId('action_id')->constrained()->onDelete('cascade');
            $table->foreignId('reaction_id')->constrained()->onDelete('cascade');
            $table->json('action_params')->nullable();
            $table->json('reaction_params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_executed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
