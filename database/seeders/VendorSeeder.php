<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Admin;

class VendorSeeder extends Seeder
{
    public function run()
    {
        $admins = Admin::all();

        $restaurants = [
            'Grill House',
            'El Sham Restaurant',
            'BBQ Nation',
            'Food Corner',
            'Taste & Go',
            'Abou Tarek',
            'El Sultan Food',
            'Cairo Kitchen',
            'Daily Meals'
        ];

        $cafes = [
            'Coffee Corner',
            'Bean House',
            'Urban Cafe',
            'Brew Hub',
            'Daily Coffee',
            'Latte Spot',
            'Espresso Time',
            'Cafe Mocha',
            'Chill Cup'
        ];

        $supermarkets = [
            'Metro Market',
            'Carrefour Express',
            'Fresh Food Market',
            'Green Basket',
            'Daily Mart',
            'Smart Shop',
            'City Market',
            'Quick Grocery',
            'Easy Shop'
        ];

        foreach (User::where('role', 'vendor')->get() as $user) {

            $type = fake()->randomElement(['restaurant', 'cafe', 'supermarket']);

            if ($type == 'restaurant') {
                $name = fake()->randomElement($restaurants);
                $logoUrl = "https://loremflickr.com/400/400/food,restaurant?lock=" . rand(1, 1000);
            } elseif ($type == 'cafe') {
                $name = fake()->randomElement($cafes);
                $logoUrl = "https://loremflickr.com/400/400/food,restaurant?lock=" . rand(1, 1000);
            } else {
                $name = fake()->randomElement($supermarkets);
                $logoUrl = "https://loremflickr.com/400/400/grocery,supermarket?lock=" . rand(1, 1000);
            }

            Vendor::create([
                'user_id' => $user->id,
                'admin_id' => $admins->random()->id,
                'business_name' => $name . ' ' . rand(1, 99),
                'vendor_type' => $type,
                'logo' => $logoUrl,

                'tax_number' => rand(10000000, 99999999),
                'commercial_register' => 'https://placehold.co/600x400/png?text=Commercial+Register',
                'tax_card' => 'https://placehold.co/600x400/png?text=Tax+Card',
            ]);
        }
    }
}