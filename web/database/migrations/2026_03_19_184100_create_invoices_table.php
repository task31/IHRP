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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->foreignId('consultant_id')->constrained('consultants');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('timesheet_id')->nullable()->constrained('timesheets')->nullOnDelete();

            // Billing snapshots (from client at invoice creation time)
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_contact')->nullable();
            $table->text('bill_to_address')->nullable();
            $table->string('payment_terms')->default('Net 30');

            $table->string('po_number')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('total_amount_due', 12, 4)->default(0);

            $table->enum('status', ['pending', 'sent', 'paid'])->default('pending');
            $table->date('sent_date')->nullable();
            $table->date('paid_date')->nullable();

            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
