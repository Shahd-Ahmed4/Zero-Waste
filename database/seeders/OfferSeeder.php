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

        // لو مفيش فروع في الداتا بيز بنوقف السيدر عشان ميحصلش إيرور
        if ($branches->isEmpty()) {
            return;
        }

        // قوالب بيانات منوعة جداً (7 أصناف لكل نوع) عشان يظهر اختلاف كبير في الأصناف
        // ✨ كومنت مضاف: تم تعديل النصوص الإنجليزي هنا لتعبّر عن أكل فائض حقيقي وصلاحية أسرع لمنع الهدر
        $content = [
            'restaurant' => [
                ['title' => 'Surplus Grilled Chicken Meal', 'description' => 'Remaining fresh grilled chicken from lunch shift, served with rice. Must be consumed tonight.'],
                ['title' => 'End of Day Shawarma Wraps', 'description' => 'Last 3 prepared chicken shawarma wraps from the afternoon batch, perfectly wrapped and warm.'],
                ['title' => 'Prepared Mix Appetizers Platter', 'description' => 'Freshly made sambousek and kibbeh platter left over from a catering order, ready to eat.'],
                ['title' => 'Crispy Chicken Strips Box', 'description' => 'A box of fried chicken pieces and fries cooked 2 hours ago. Still crunchy and delicious.'],
                ['title' => 'Over-prepared Beef Burgers', 'description' => 'Extra double beef burgers prepared for dinner rush. High quality and ready for quick pickup.'],
                ['title' => 'Daily Baked Pasta Pan', 'description' => 'A full tray portion of baked lasagna bolognese, kept fresh in the oven. Final clearance before closing.'],
                ['title' => 'Mixed Oriental Rice Bowl', 'description' => 'Fresh basmati rice bowl topped with kofta pieces from today’s lunch shift.'],
            ],
            'cafe' => [
                ['title' => 'Morning Croissant Clearance', 'description' => 'Butter and cheese croissants baked fresh this morning. Perfect evening snack before they harden.'],
                ['title' => 'Assorted Donuts Box (Save Food)', 'description' => '6 pieces of delicious glazed donuts remaining from today’s display window.'],
                ['title' => 'Fresh Cold Club Sandwiches', 'description' => 'Turkey and cheddar cold club sandwiches made today. Safe to consume within 6 hours.'],
                ['title' => 'Remaining Chocolate Brownies', 'description' => '3 pieces of rich chocolate brownie squares left from the daily cafe display.'],
                ['title' => 'Carrot Cake Slices Promo', 'description' => 'Last two fresh slices of premium carrot cake with cream frosting. Must sell tonight.'],
                ['title' => 'Fresh Blueberry Muffin Pack', 'description' => 'A bakery box containing 4 fluffy muffins baked in the morning shift.'],
                ['title' => 'Cookies & Danish Bag', 'description' => 'A mix of chocolate chip cookies and fruit danish pastries left over from the afternoon.'],
            ],
            'supermarket' => [
                ['title' => 'Ripe Seasonal Fruit Basket', 'description' => 'A mix of fully ripe apples, bananas, and grapes. Perfect for immediate eating or making juice.'],
                ['title' => 'Daily Dairy & Yogurt Bundle', 'description' => 'Premium milk bottles and fresh yogurt cups with expiry dates within the next 48 hours.'],
                ['title' => 'Sliced Cold Cuts Platter', 'description' => 'Smoked turkey and roast beef slices packed today. Needs to be kept refrigerated and consumed soon.'],
                ['title' => 'Grocery Essential Short-Date Pack', 'description' => 'Bundle of premium tomato sauce and cooking oil approaching their display deadline.'],
                ['title' => 'Healthy Granola & Nut Bars', 'description' => 'A collection of organic protein and fruit bars with a close expiry date.'],
                ['title' => 'Fresh Vegetable Mix Box', 'description' => '3kg of fresh organic tomatoes, cucumbers, and bell peppers that need to be used tonight.'],
            ],
            'bakery' => [ // ✨ كومنت مضاف: قوالب المخابز الجديدة المتناسقة مع الويب
                ['title' => 'Evening Baguette Discount', 'description' => '3 pieces of traditional French baguettes baked this afternoon. Best enjoyed tonight.'],
                ['title' => 'Sliced Fudge Cake Clearance', 'description' => 'Last remaining slices of today’s premium chocolate fudge cake.'],
                ['title' => 'Mixed Patisserie Sweet Box', 'description' => 'An assortment of mini gateaux and eastern sweets left from the morning bakery production.'],
                ['title' => 'Soft Dinner Rolls Pack', 'description' => 'A package of 12 soft milk buns and rolls baked early today, perfect for dinner.'],
            ],
            'hotel' => [ // ✨ كومنت مضاف: قوالب الفنادق الجديدة الفخمة
                ['title' => 'Lunch Buffet Surplus Ticket', 'description' => 'Access voucher for the premium 5-star open buffet dinner, clearing out the fresh kitchen creations.'],
                ['title' => 'Late Afternoon High Tea Box', 'description' => 'Luxury afternoon tea sweets, scones, and savory finger sandwiches packed fresh after service.'],
                ['title' => 'Fresh Live-Station Sushi Platter', 'description' => '12 pieces of assorted sushi rolls prepared during the midday buffet by our head chef.'],
                ['title' => 'Premium International Pastry Pack', 'description' => 'A luxury box containing French tarts and desserts from the hotel’s morning bakery lounge.'],
            ],
        ];

        // مصفوفة الصور المنوعة اللي موجودة عندك في الـ public/uploads
        $images = [
            'restaurant' => ['uploads/restaurant1.jpg', 'uploads/restaurant2.jpg', 'uploads/restaurant3.jpg', 'uploads/restaurant4.jpg'],
            'cafe' => ['uploads/cafe1.jpg', 'uploads/cafe2.jpg', 'uploads/cafe3.jpg', 'uploads/cafe4.jpg', 'uploads/cafe5.jpg'],
            'supermarket' => ['uploads/market1.jpg', 'uploads/market2.jpg', 'uploads/market3.jpg','uploads/market4.jpg', 'uploads/market5.jpg'],
            'bakery' => ['uploads/cafe1.jpg', 'uploads/cafe2.jpg', 'uploads/cafe3.jpg', 'uploads/cafe4.jpg', 'uploads/cafe5.jpg'], // ✨ كومنت مضاف: ربط صور المخبوزات
            'hotel' => ['uploads/restaurant1.jpg', 'uploads/restaurant2.jpg', 'uploads/restaurant3.jpg', 'uploads/restaurant4.jpg'], // ✨ كومنت مضاف: ربط صور بوفيه الفنادق
        ];

        // ✨ كومنت مضاف: هنا عدلنا اللوب لتلف 25 لفة بالظبط عشان ننتج 25 عرض فقط في قاعدة البيانات
        for ($i = 0; $i < 25; $i++) {
            
            // بننقي فرع عشوائي من الفروع المتاحة
            $branch = $branches->random();

            if (!$branch->vendor) {
                $i--; // لو الفرع ملوش تاجر بنرجع خطوة لضمان اكتمال الـ 25 عرض بالظبط
                continue;
            }

            $vendorType = strtolower(trim($branch->vendor->vendor_type));

            if ($vendorType === 'restaurants') {
                $vendorType = 'restaurant';
            }

            // ✨ كومنت مضاف: شروط تحويل وتأكيد الأنواع الجديدة لتطابق المصفوفات
            if ($vendorType === 'bakeries') { $vendorType = 'bakery'; }
            if ($vendorType === 'hotels') { $vendorType = 'hotel'; }

            if (!array_key_exists($vendorType, $content)) {
                $vendorType = 'restaurant';
            }
            
            // الكود هينقي بشكل عشوائي من الأصناف بتوع النوع الحالي
            $template = fake()->randomElement($content[$vendorType]);
            $randomImage = fake()->randomElement($images[$vendorType]);
            
            $originalPrice = rand(120, 500);
            $discountPercentage = rand(15, 50) / 100;
            $discountPrice = $originalPrice * (1 - $discountPercentage);

            offer::create([
                'branch_id' => $branch->id,
                'title' => $template['title'],
                'description' => $template['description'],
                'image' => $randomImage,
                
                // 🔥 تعديل الكمية المتاحة لتكون في الرنج المطلوب (من 1 لـ 10 بس)
                'quantity_available' => rand(1, 10),
                
                'original_price' => $originalPrice,
                'discount_price' => round($discountPrice, 2),
                'expiration_time' => Carbon::now()->addHours(rand(2, 15)),
                'status' => 'active',
            ]);
        }
    }
}