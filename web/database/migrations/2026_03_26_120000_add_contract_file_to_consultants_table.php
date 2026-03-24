<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            $table->string('contract_file_path')->nullable()->after('w9_file_path');
            $table->boolean('contract_on_file')->default(false)->after('contract_file_path');
        });

        foreach (DB::table('consultants')->pluck('id') as $cid) {
            $exists = DB::table('consultant_onboarding_items')
                ->where('consultant_id', $cid)
                ->where('item_key', 'msa_contract')
                ->exists();
            if (! $exists) {
                DB::table('consultant_onboarding_items')->insert([
                    'consultant_id' => $cid,
                    'item_key' => 'msa_contract',
                    'completed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('consultant_onboarding_items')->where('item_key', 'msa_contract')->delete();

        Schema::table('consultants', function (Blueprint $table) {
            $table->dropColumn(['contract_file_path', 'contract_on_file']);
        });
    }
};
