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
        Schema::table('consultants', function (Blueprint $table) {
            $table->decimal('pay_rate', 12, 4)->nullable()->default(null)->change();
            $table->decimal('bill_rate', 12, 4)->nullable()->default(null)->change();
            $table->string('state')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            $table->decimal('pay_rate', 12, 4)->nullable(false)->default(0)->change();
            $table->decimal('bill_rate', 12, 4)->nullable(false)->default(0)->change();
            $table->string('state')->nullable(false)->default('')->change();
        });
    }
};
