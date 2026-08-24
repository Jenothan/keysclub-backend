<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Esan Jenothan',
            'phone' => '+94763326098',
            'email' => 'esanjenothan@gmail.com',
            'password' => Hash::make('Jeno@1234'),
            'role' => 'Super Admin',
            'phone_verified_at' => now(),
        ]);
    }
}
