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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('billing_contact_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('email')->nullable();
            $table->string('smtp_email')->nullable();

            $table->string('payment_terms')->default('Net 30');
            $table->decimal('total_budget', 12, 4)->nullable();
            $table->boolean('budget_alert_warning_sent')->default(false);
            $table->boolean('budget_alert_critical_sent')->default(false);

            $table->string('po_number')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
