<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'admin@matchpointegroup.com',
        ], [
            'name' => 'Admin',
            'password' => Hash::make(env('ADMIN_PASSWORD', Str::random(24))),
            'role' => 'admin',
            'active' => true,
        ]);

        if (app()->environment('local')) {
            $this->call(EmailInboxDemoSeeder::class);
        }
    }
}
