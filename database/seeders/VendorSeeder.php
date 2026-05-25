<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\vendor;
use App\Models\User;
use App\Models\admin;

class VendorSeeder extends Seeder
{
    public function run()
    {
        $admins = admin::all();

        // ربط كل اسم محل بالدومين الحقيقي بتاعه عشان جوجل يجيب اللوجو المظبوط
        $restaurants = [
            ['name' => 'McDonalds', 'domain' => 'mcdonalds.com'],
            ['name' => 'KFC', 'domain' => 'kfc.com'],
            ['name' => 'Pizza Hut', 'domain' => 'pizzahut.com'],
            ['name' => 'Burger King', 'domain' => 'bk.com'],
            ['name' => 'Subway', 'domain' => 'subway.com'],
            ['name' => 'Dominos Pizza', 'domain' => 'dominos.com'],
            ['name' => 'Hardees', 'domain' => 'hardees.com'],
            ['name' => 'Papa Johns', 'domain' => 'papajohns.com'],
            ['name' => 'El Malki', 'domain' => 'elmalki-eg.com'] // التعديل هنا فقط بدلاً من كشري أبو طارق
        ];

        $cafes = [
            ['name' => 'Starbucks', 'domain' => 'starbucks.com'],
            ['name' => 'Costa Coffee', 'domain' => 'costacoffee.com'],
            ['name' => 'Dunkin Donuts', 'domain' => 'dunkindonuts.com'],
            ['name' => 'Cinnabon', 'domain' => 'cinnabon.com'],
            ['name' => 'Caribou Coffee', 'domain' => 'cariboucoffee.com'],
            ['name' => 'Cafe Supreme', 'domain' => 'cafesupreme.com'],
            ['name' => 'Cilantro', 'domain' => 'cilantrocafe.com'],
            ['name' => 'Beano\'s', 'domain' => 'beanoscafe.com'],
            ['name' => 'TBS (The Bakery Shop)', 'domain' => 'tbs-bakery.com']
        ];

        $supermarkets = [
            ['name' => 'Carrefour', 'domain' => 'carrefour.com'],
            ['name' => 'Metro Market', 'domain' => 'metro-markets.com'],
            ['name' => 'Spinneys', 'domain' => 'spinneys-egypt.com'],
            ['name' => 'Kheir Zaman', 'domain' => 'khair-zaman.com'],
            ['name' => 'Panda', 'domain' => 'panda.com.sa'],
            ['name' => 'Lulu Hypermarket', 'domain' => 'luluhypermarket.com'],
            ['name' => 'Gourmet Egypt', 'domain' => 'gourmetegypt.com'],
            ['name' => 'Seoudi Market', 'domain' => 'seoudimarket.com'],
            ['name' => 'Alfa Market', 'domain' => 'alfamarket.com.eg']
        ];

        $bakeries = [
            ['name' => 'La Poire Bakery', 'domain' => 'lapoire.com'],
            ['name' => 'Monginis', 'domain' => 'monginis.net'],
            ['name' => 'El Abd Patisserie', 'domain' => 'elabdfoods.com'],
            ['name' => 'Tseppas', 'domain' => 'tseppas.com'],
            ['name' => 'Salé Sucré', 'domain' => 'salesucre.com'],
            ['name' => 'Nola Cupcakes', 'domain' => 'nolacupcakes.com'],
            ['name' => 'Coppermelt', 'domain' => 'coppermelt.net'],
            ['name' => 'Simonds Bakery', 'domain' => 'simonds-bakery.com'],
            ['name' => 'House of Donuts', 'domain' => 'houseofdonuts.com']
        ];

        $hotels = [
            ['name' => 'Hilton Hotels', 'domain' => 'hilton.com'],
            ['name' => 'Sheraton Hotels', 'domain' => 'marriott.com/sheraton'],
            ['name' => 'Four Seasons', 'domain' => 'fourseasons.com'],
            ['name' => 'Fairmont Luxury', 'domain' => 'fairmont.com'],
            ['name' => 'Ritz Carlton', 'domain' => 'ritzcarlton.com'],
            ['name' => 'Movenpick', 'domain' => 'movenpick.com'],
            ['name' => 'Steigenberger', 'domain' => 'steigenberger.com'],
            ['name' => 'Sofitel Stay', 'domain' => 'sofitel.com'],
            ['name' => 'Novotel Hotel', 'domain' => 'novotel.com']
        ];

        foreach (User::where('role', 'vendor')->get() as $user) {

            $type = fake()->randomElement(['restaurant', 'cafe', 'supermarket', 'bakery', 'hotel']);

            // اختيار العنصر العشوائي كـ Array (يحتوي على الاسم والدومين معاً)
            if ($type == 'restaurant') {
                $selected = fake()->randomElement($restaurants);
            } elseif ($type == 'cafe') {
                $selected = fake()->randomElement($cafes);
            } elseif ($type == 'bakery') { 
                $selected = fake()->randomElement($bakeries);
            } elseif ($type == 'hotel') { 
                $selected = fake()->randomElement($hotels);
            } else {
                $selected = fake()->randomElement($supermarkets);
            }

            $name = $selected['name'];
            // تمرير الدومين المخصص لكل محل لجوجل بشكل ديناميكي ومستحيل يضرب
            $logoUrl = "https://www.google.com/s2/favicons?domain=" . $selected['domain'] . "&sz=128";

            
            // التعديل هنا: الإشارة إلى مسار الصور التي وضعتِها في مجلد public
              $commercialRegisterUrl = 'uploads/commreg.jpeg'; 
               $taxCardUrl = 'uploads/taxcard.jpeg';  

            vendor::create([
                'user_id' => $user->id,
                'admin_id' => $admins->isEmpty() ? null : $admins->random()->id,
                'business_name' => $name . ' ' . rand(1, 99),
                'vendor_type' => $type,
                'logo' => $logoUrl,

                'tax_number' => rand(10000000, 99999999),
                'commercial_register' => $commercialRegisterUrl,
                'tax_card' => $taxCardUrl,
            ]);
        }
    }
}