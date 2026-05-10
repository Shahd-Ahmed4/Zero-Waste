<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\branch;
use App\Models\offer;
use Carbon\Carbon;

class OfferSeeder extends Seeder
{
    public function run()
    {
        // بنجيب كل الفروع ومعاها بيانات الـ Vendor عشان نعرف نوع النشاط
        $branches = branch::with('vendor')->get();

        // قوالب بيانات واقعية بالإنجليزية مقسمة حسب نوع الـ Vendor
        $content = [
        'restaurant' => [
            ['title' => 'Family Grill Meal', 'description' => 'Includes kofta, grilled chicken, rice, and salads. Fresh daily surplus.', 'keyword' => 'grill'],
            ['title' => 'Single Mix Grill Offer', 'description' => 'Hot meal ready to eat, prepared fresh today.', 'keyword' => 'meat'],
            ['title' => 'Oriental Appetizer Platter', 'description' => 'Assortment of sambousek, kibbeh, and fresh appetizers.', 'keyword' => 'appetizer'],
        ],
        'cafe' => [
            ['title' => 'Morning Pastry Box', 'description' => 'Fresh croissants and pates from today’s morning production.', 'keyword' => 'croissant'],
            ['title' => 'Assorted Donuts Box', 'description' => '6 pieces of donuts with various flavors in excellent condition.', 'keyword' => 'donuts'],
            ['title' => 'Club & Caesar Sandwiches', 'description' => 'A set of carefully prepared cold sandwiches.', 'keyword' => 'sandwich'],
        ],
        'supermarket' => [
            ['title' => 'Mixed Vegetable Box', 'description' => 'Fresh variety of vegetables (tomatoes, cucumbers, peppers) weighing 3kg.', 'keyword' => 'vegetables'],
            ['title' => 'Seasonal Fruit Basket', 'description' => 'Assorted fruits from the fresh section, suitable for immediate consumption.', 'keyword' => 'fruits'],
            ['title' => 'Dairy Products Box', 'description' => 'Includes cheeses and milk nearing their surplus date.', 'keyword' => 'dairy'],
        ],
        'bakery' => ['Special bread offer', 'Buy 2 get 1 free'], // ضيفي دي
    ];

        foreach ($branches as $branch) {
            // بنحدد نوع الـ Vendor عشان نختار الأكل المناسب ليه
            $vendorType = $branch->vendor->vendor_type ?? 'restaurant';

            // تنوع في عدد العروض لكل فرع (من 1 لـ 4 عروض) عشان الواقعية
            $offerCount = rand(1, 4);

            for ($i = 0; $i < $offerCount; $i++) {
                // اختيار قالب عشوائي بناءً على النوع (Restaurant, Cafe, Supermarket)
                $template = fake()->randomElement($content[$vendorType]);

                // تحديد سعر أصلي عشوائي بين 120 و 500 جنيه
                $originalPrice = rand(120, 500);

                // تطبيق منطق الخصم المطلوب (بين 15% و 50%)
                $discountPercentage = rand(15, 50) / 100;
                $discountPrice = $originalPrice * (1 - $discountPercentage);
                $imageUrl = "https://loremflickr.com/600/400/" . $template['keyword'] . "?lock=" . rand(1, 1000);

                offer::create([
                    'branch_id' => $branch->id,
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'image' => $imageUrl, // ممكن ترفعي صور وتضيفي مساراتها هنا لاحقاً
                    'quantity_available' => rand(2, 20),
                    'original_price' => $originalPrice,
                    'discount_price' => round($discountPrice, 2), // تقريب السعر لقرشين عشريين

                    // وقت الانتهاء: إضافة ساعات عشوائية (من 2 لـ 15) من الوقت الحالي
                    'expiration_time' => Carbon::now()->addHours(rand(2, 15)),

                    'status' => 'active',
                ]);
            }
        }
    }
}