<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 👑 Admins (3)
        User::factory()->count(3)->create([
            'role' => 'admin'
        ]);

        // 🏪 Vendors (80)
        User::factory()->count(80)->create([
            'role' => 'vendor'
        ]);

        // 🧑‍💻 Customers (100)
        User::factory()->count(100)->create([
            'role' => 'customer'
        ]);
    }
}