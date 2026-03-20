<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'employee')->update(['role' => 'account_manager']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'account_manager') NOT NULL DEFAULT 'account_manager'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'account_manager', 'employee') NOT NULL DEFAULT 'employee'");
    }
};
