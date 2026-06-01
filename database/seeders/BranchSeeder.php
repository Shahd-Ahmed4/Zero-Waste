<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\vendor;
use App\Models\branch;

class BranchSeeder extends Seeder
{
    public function run()
    {
        $vendors = vendor::all();

        foreach ($vendors as $vendor) {
            // منطق واقعي: عدد الفروع يعتمد على نوع الـ Vendor
            // ✨ كومنت مضاف: أضفنا الـ hotel مع السوبرماركت والـ bakery مع المطاعم ليعمل بنفس المنطق الأصلي
            if ($vendor->vendor_type == 'supermarket' || $vendor->vendor_type == 'hotel') {
                // السوبر ماركت غالباً بيبقى سلسلة (من 3 لـ 5 فروع)
                $branchCount = rand(3, 5);
            } elseif ($vendor->vendor_type == 'restaurant' || $vendor->vendor_type == 'bakery') {
                // المطاعم غالباً فرع رئيسي وممكن فرع كمان (من 1 لـ 2)
                $branchCount = rand(1, 2);
            } else {
                // الكافيهات (من 1 لـ 3)
                $branchCount = rand(1, 3);
            }

            for ($i = 1; $i <= $branchCount; $i++) {
                // تحديد منطقة لكل فرع عشان الواقعية
                $locations = [
                    ['name' => '15 Talaat Harb St, Downtown', 'lat' => 30.0444, 'long' => 31.2357], // وسط البلد
                    ['name' => 'South 90th St, New Cairo', 'lat' => 30.0074, 'long' => 31.4913],
                    ['name' => 'Central Axis, Sheikh Zayed', 'lat' => 30.0161, 'long' => 30.9833],
                    ['name' => '21 Road 9, Maadi', 'lat' => 29.9602, 'long' => 31.2569],
                    ['name' => '45 Abbas El Akkad St, Nasr City', 'lat' => 30.0561, 'long' => 31.3301],
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