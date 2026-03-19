<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('invoice_sequence')->where('id', 1)->doesntExist()) {
            DB::table('invoice_sequence')->insert([
                'id' => 1,
                'prefix' => '',
                'next_number' => 1,
                'fiscal_year_start' => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('invoice_sequence')->where('id', 1)->delete();
    }
};
