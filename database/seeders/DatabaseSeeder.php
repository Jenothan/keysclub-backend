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
        // Create Super Admin (if not existing)
        User::firstOrCreate(
            ['email' => 'esanjenothan@gmail.com'],
            [
                'name' => 'Esan Jenothan',
                'phone' => '+94763326098',
                'password' => Hash::make('Jeno@1234'),
                'role' => 'Super Admin',
                'phone_verified_at' => now(),
            ]
        );

        // Seed default Courts if missing
        \App\Models\Court::firstOrCreate(['name' => 'Court 1'], ['type' => 'Indoor', 'status' => true]);
        \App\Models\Court::firstOrCreate(['name' => 'Court 2'], ['type' => 'Indoor', 'status' => true]);

        // Seed default Website Data if missing
        if (\App\Models\WebsiteData::count() === 0) {
            \App\Models\WebsiteData::create([
                'primary_phone' => '+94 76 332 6098',
                'support_email' => 'keysclub@gmail.com',
                'club_address' => 'Karanavai East, Karaveddy, Jaffna',
                'court_pricing' => '400',
                'membership_pricing' => '1000',
                'registration_fee' => '2000',
                'full_day_pricing' => '3000',
            ]);
        }
    }
}
