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
        Schema::create('consultant_onboarding_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultant_id')->constrained('consultants')->cascadeOnDelete();
            $table->string('item_key');
            $table->boolean('completed')->default(false);

            $table->timestamps();

            $table->unique(['consultant_id', 'item_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultant_onboarding_items');
    }
};
