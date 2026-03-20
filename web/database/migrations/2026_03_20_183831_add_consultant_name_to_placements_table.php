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
        Schema::table('placements', function (Blueprint $table) {
            $table->dropForeign(['consultant_id']);
            $table->unsignedBigInteger('consultant_id')->nullable()->change();
            $table->string('consultant_name', 255)->nullable()->after('consultant_id');
        });
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->dropColumn('consultant_name');
            $table->unsignedBigInteger('consultant_id')->nullable(false)->change();
            $table->foreign('consultant_id')->references('id')->on('consultants')->cascadeOnDelete();
        });
    }
};
