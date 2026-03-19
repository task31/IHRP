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
        Schema::create('timesheet_daily_hours', function (Blueprint $table) {
            $table->id();

            $table->foreignId('timesheet_id')->constrained('timesheets')->cascadeOnDelete();

            $table->string('day_of_week');
            $table->tinyInteger('week_number');
            $table->decimal('hours', 6, 2)->default(0);

            $table->unique(
                ['timesheet_id', 'week_number', 'day_of_week'],
                'tdh_timesheet_week_day_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheet_daily_hours');
    }
};
