<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AdminSeeder::class,
            CustomerSeeder::class,
            VendorSeeder::class,
            BranchSeeder::class,
            OfferSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
