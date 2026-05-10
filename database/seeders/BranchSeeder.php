<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;
use App\Models\branch;

class BranchSeeder extends Seeder
{
    public function run()
    {
        $vendors = Vendor::all();

        foreach ($vendors as $vendor) {
            // منطق واقعي: عدد الفروع يعتمد على نوع الـ Vendor
            if ($vendor->vendor_type == 'supermarket') {
                // السوبر ماركت غالباً بيبقى سلسلة (من 3 لـ 5 فروع)
                $branchCount = rand(3, 5);
            } elseif ($vendor->vendor_type == 'restaurant') {
                // المطاعم غالباً فرع رئيسي وممكن فرع كمان (من 1 لـ 2)
                $branchCount = rand(1, 2);
            } else {
                // الكافيهات (من 1 لـ 3)
                $branchCount = rand(1, 3);
            }

            for ($i = 1; $i <= $branchCount; $i++) {
                // تحديد منطقة لكل فرع عشان الواقعية
                $locations = [
                    ['name' => 'Main Branch', 'lat' => 30.0444, 'long' => 31.2357], // وسط البلد
                    ['name' => 'New Cairo Branch', 'lat' => 30.0074, 'long' => 31.4913],
                    ['name' => 'Sheikh Zayed Branch', 'lat' => 30.0161, 'long' => 30.9833],
                    ['name' => 'Maadi Branch', 'lat' => 29.9602, 'long' => 31.2569],
                    ['name' => 'Nasr City Branch', 'lat' => 30.0561, 'long' => 31.3301],
                ];

                $location = $locations[$i - 1] ?? $locations[0];

                branch::create([
                    'vendor_id' => $vendor->id,
                    'branch_name' => $vendor->business_name . ' - ' . $location['name'],
                    'opening_hours' => '08:00 AM - 12:00 AM',
                    'store_address' => $location['name'] . ', Cairo, Egypt',
                    'contact_email' => strtolower(str_replace(' ', '.', $vendor->business_name)) . ".br$i@example.com",
                    'contact_phone' => '01' . rand(100000000, 199999999),
                    // إضافة تباين بسيط جداً في الـ Lat/Long عشان ميبقوش فوق بعض بالظبط
                    'lat' => $location['lat'] + (rand(-10, 10) / 1000),
                    'long' => $location['long'] + (rand(-10, 10) / 1000),
                    'status' => 'active',
                ]);
            }
        }
    }
}