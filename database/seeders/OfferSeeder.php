<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\branch;
use App\Models\offer;
use Carbon\Carbon;
use Illuminate\Support\Str;
class OfferSeeder extends Seeder
{
    public function run()
    {
        $branches = branch::with('vendor')->get();

        if ($branches->isEmpty()) {
            return;
        }

        // تفكيك الأكلات بدقة شديدة بناءً على براند المحل نفسه
        $specificContent = [
            // --- المطاعم (Restaurants) ---
            'kfc' => [
                ['title' => 'Crispy Chicken Strips Box', 'description' => 'A box of fried chicken pieces and family-size fries cooked during afternoon shift.'],
                ['title' => 'Surplus Grilled Chicken Meal', 'description' => 'Remaining fresh grilled chicken from lunch shift, served with rice and garlic dip.'],
                ['title' => 'Mighty Zinger Meal Clearance', 'description' => 'Extra spicy chicken zinger sandwiches left from the dinner rush bundle.'],
            ],
            'mcdonalds' => [
                ['title' => 'Over-prepared Double Beef Burger', 'description' => 'Extra double beef burgers prepared for dinner rush. High quality and ready for quick pickup.'],
                ['title' => 'Crispy Chicken Sandwich Promo', 'description' => 'Crispy chicken sandwich with lettuce and mayo sauce, ready for instant takeaway.'],
                ['title' => 'Big Mac Cheese Burger Box', 'description' => 'Surplus of the famous big mac beef burgers kept fresh before closing.'],
            ],
            'burger king' => [
                ['title' => 'Whopper Beef Burger Surplus', 'description' => 'Extra flame-grilled Whopper burgers prepared for evening rush.'],
                ['title' => 'Chicken Royale Meal Deal', 'description' => 'Remaining chicken royale sandwiches cooked during afternoon shift.'],
            ],
            'pizza hut' => [
                ['title' => 'Daily Baked Pasta Pan Portion', 'description' => 'A full tray portion of baked lasagna bolognese, kept fresh in the oven before closing.'],
                ['title' => 'Super Supreme Pizza Slices Bag', 'description' => 'Large pizza slices leftover from canceled catering order, perfectly safe.'],
            ],
            'dominos' => [
                ['title' => 'Pepperoni Pizza Clearance Portion', 'description' => 'Freshly baked pepperoni pizza slices from afternoon batch.'],
                ['title' => 'Cheesy Garlic Bread Basket', 'description' => 'Freshly prepared garlic bread with melted mozzarella cheese leftover.'],
            ],
            'hardees' => [
                ['title' => 'Super Star Beef Burger Option', 'description' => 'Extra premium beef burgers prepared during dinner shift.'],
            ],
            'shawarma' => [
                ['title' => 'End of Day Chicken Shawarma Wrap', 'description' => 'Delicious prepared chicken shawarma wrap from afternoon batch, perfectly wrapped.'],
                ['title' => 'Beef Shawarma Fattah Platter', 'description' => 'Fresh fatteh tray with meat shawarma slices leftover from evening service.'],
            ],
            'kushari' => [
                ['title' => 'Traditional Egyptian Kushari Box', 'description' => 'Large size kushari box with extra tomato sauce and crispy onions packed fresh today.'],
                ['title' => 'Rice Pudding with Nuts Dessert', 'description' => 'Freshly prepared cold rice pudding cups from the dairy display.'],
            ],
            'generic_restaurant' => [
                ['title' => 'Prepared Mix Appetizers Platter', 'description' => 'Freshly made sambousek and kibbeh platter left over from a catering order.'],
                ['title' => 'Mixed Oriental Kofta Rice Bowl', 'description' => 'Fresh basmati rice bowl topped with grilled kofta pieces and tahini sauce.'],
                ['title' => 'Family Size Mix Grill Clearance', 'description' => 'A premium mix of kebab and kofta items left from evening buffet preparation.'],
            ],

            // --- الكافيهات (Cafes) ---
            'starbucks' => [
                ['title' => 'Iced Latte & Sandwich Bundle', 'description' => 'Perfect afternoon combo including a fresh cheese sandwich and a chilled beverage cup.'],
                ['title' => 'Morning Butter Croissant Clearance', 'description' => 'Classic butter croissants baked fresh this morning.'],
                ['title' => 'Caramel Macchiato Bakery Combo', 'description' => 'Muffin and sweet bakery item packaged with special promo.'],
            ],
            'dunkin' => [
                ['title' => 'Assorted Glazed Donuts Box', 'description' => '4 pieces of delicious glazed and chocolate donuts remaining from daily display window.'],
                ['title' => 'Choco Sprinkle Donut Duo', 'description' => 'Two fresh chocolate donuts baked in the morning shift.'],
            ],
            'cinnabon' => [
                ['title' => 'Apple Cinnamon Danish Pastry', 'description' => 'Sweet danish pastry filled with baked apple chunks and cinnamon powder glaze.'],
                ['title' => 'Classic Cinnabon Roll Clearance', 'description' => 'Fresh cinnabon rolls baked hours ago, perfect for instant microwave heat.'],
            ],
            'generic_cafe' => [
                ['title' => 'Fresh Turkey Club Sandwich', 'description' => 'Turkey and cheddar cold club sandwiches made today.'],
                ['title' => 'Remaining Chocolate Fudge Brownie', 'description' => 'Rich chocolate brownie squares left from the daily cafe display case.'],
                ['title' => 'Premium Carrot Cake Slice Promo', 'description' => 'Last fresh slice of premium carrot cake with rich cream cheese frosting.'],
                ['title' => 'Fresh Blueberry Muffin Pack', 'description' => 'A bakery box containing 2 fluffy blueberry muffins baked in morning shift.'],
            ],

            // --- المخابز والحلويات (Bakeries) ---
            'el abd' => [
                ['title' => 'Traditional Honey Basbousa Tray', 'description' => 'Half a tray of rich oriental basbousa with almonds and pure honey syrup.'],
                ['title' => 'Mixed Patisserie Eastern Sweet Box', 'description' => 'An assortment of mini gateaux and eastern sweets left from morning production.'],
                ['title' => 'Kahk and Ghorayeba Small Bag', 'description' => 'Premium Egyptian bakery cookies packed from today\'s window.'],
            ],
            'monginis' => [
                ['title' => 'Sliced Chocolate Fudge Cake', 'description' => 'Last remaining slices of today’s premium chocolate fudge cake from counter.'],
                ['title' => 'Vanilla Swiss Roll Piece Box', 'description' => 'Freshly sliced vanilla sponge cake rolls leftover.'],
            ],
            'generic_bakery' => [
                ['title' => 'Evening French Baguette Discount', 'description' => '3 pieces of traditional French baguettes baked this afternoon.'],
                ['title' => 'Soft Milk Dinner Rolls Pack', 'description' => 'A package of 12 soft milk buns and rolls baked early today.'],
                ['title' => 'Freshly Baked White Toast Bread', 'description' => 'Large sliced white toast bread loaf baked in morning shift.'],
            ],

            // --- السوبرماركت (Supermarkets) ---
            'generic_supermarket' => [
                ['title' => 'Ripe Seasonal Fruit Basket', 'description' => 'A mix of fully ripe apples, bananas, and grapes.'],
                ['title' => 'Daily Dairy & Yogurt Bundle', 'description' => 'Premium milk bottles and fresh yogurt cups with short expiry dates.'],
                ['title' => 'Sliced Turkey Cold Cuts Platter', 'description' => 'Smoked turkey and roast beef slices packed today.'],
                ['title' => 'Grocery Essential Short-Date Pack', 'description' => 'Bundle of premium tomato sauce and organic cooking oil.'],
                ['title' => 'Healthy Granola & Nut Bars Pack', 'description' => 'A collection of organic protein and fruit granola bars.'],
            ],

            // --- الفنادق (Hotels) ---
            'generic_hotel' => [
                ['title' => 'Lunch Buffet Surplus Ticket', 'description' => 'Voucher to collect a full meal box packed fresh from 5-star open buffet.'],
                ['title' => 'Late Afternoon High Tea Box', 'description' => 'Luxury afternoon tea sweets, scones, and savory finger sandwiches.'],
                ['title' => 'Fresh Live-Station Sushi Platter', 'description' => '12 pieces of assorted sushi rolls prepared by executive chef.'],
                ['title' => 'Premium International Pastry Pack', 'description' => 'A luxury box containing French tarts from morning lounge.'],
            ],
        ];

        // 🎯 خريطة تحدد عدد الصور الفعلي المتاح عندك في فولدر public/uploads لكل براند
        // (تقدري تعدلي الأرقام دي هنا فوراً لو زودتي أو قللتي عدد الصور لأي براند)
        $maxImagesPerBrand = [
            'kfc' => 3,
            'mcdonalds' => 3,
            'burger king' => 2,
            'pizza hut' => 2,
            'dominos' => 2,
            'hardees' => 1,
            'shawarma' => 2,
            'kushari' => 2,
            'starbucks' => 3,
            'dunkin' => 2,
            'cinnabon' => 2,
            'el abd' => 3,
            'monginis' => 2,
            'generic_restaurant' => 3,
            'generic_cafe' => 4,
            'generic_bakery' => 3,
            'generic_supermarket' => 5,
            'generic_hotel' => 4,
        ];

        $totalOffersCreated = 0;
        $maxOffers = 100;
        $disabledCount = 0;

        while ($totalOffersCreated < $maxOffers) {
            foreach ($branches as $branch) {
                if ($totalOffersCreated >= $maxOffers) {
                    break;
                }

                if (!$branch->vendor) {
                    continue;
                }

                $vendorType = strtolower(trim($branch->vendor->vendor_type));
                $businessName = strtolower($branch->vendor->business_name);
                $originalName = $branch->vendor->business_name;

                if ($vendorType === 'restaurant') {
                    $vendorType = 'restaurant';
                }
                if ($vendorType === 'bakeries') {
                    $vendorType = 'bakery';
                }
                if ($vendorType === 'hotels') {
                    $vendorType = 'hotel';
                }

                // ⚡ تحديد الـ Brand Key المظبوط عشان نربطه بالمنيو وبالصور ديناميكياً
                $brandKey = 'generic_restaurant';
                $menuPool = [];

                if ($vendorType === 'restaurant') {
                    if (Str::contains($businessName, 'kfc')) {
                        $brandKey = 'kfc';
                    } elseif (Str::contains($businessName, 'mcdonald')) {
                        $brandKey = 'mcdonalds';
                    } elseif (Str::contains($businessName, 'burger king')) {
                        $brandKey = 'burger king';
                    } elseif (Str::contains($businessName, 'pizza hut')) {
                        $brandKey = 'pizza hut';
                    } elseif (Str::contains($businessName, 'domino')) {
                        $brandKey = 'dominos';
                    } elseif (Str::contains($businessName, 'hardee')) {
                        $brandKey = 'hardees';
                    } elseif (Str::contains($businessName, 'shawarma')) {
                        $brandKey = 'shawarma';
                    } elseif (Str::contains($businessName, 'malki') || Str::contains($businessName, 'tariq')) {
                        $brandKey = 'kushari';
                    } else {
                        $brandKey = 'generic_restaurant';
                    }
                } elseif ($vendorType === 'cafe') {
                    if (Str::contains($businessName, 'starbucks')) {
                        $brandKey = 'starbucks';
                    } elseif (Str::contains($businessName, 'dunkin')) {
                        $brandKey = 'dunkin';
                    } elseif (Str::contains($businessName, 'cinnabon')) {
                        $brandKey = 'cinnabon';
                    } else {
                        $brandKey = 'generic_cafe';
                    }
                } elseif ($vendorType === 'bakery') {
                    if (Str::contains($businessName, 'abd')) {
                        $brandKey = 'el abd';
                    } elseif (Str::contains($businessName, 'monginis')) {
                        $brandKey = 'monginis';
                    } else {
                        $brandKey = 'generic_bakery';
                    }
                } elseif ($vendorType === 'hotel') {
                    $brandKey = 'generic_hotel';
                } else {
                    $brandKey = 'generic_supermarket';
                }

                // سحب المنيو بناءً على الـ Brand Key المختار
                $menuPool = $specificContent[$brandKey] ?? $specificContent['generic_restaurant'];

                // لخبطة المنيو الخاص بالمحل ده قبل التوزيع

                $availableDishesCount = count($menuPool);
                $branchOffersCount = rand(1, min($availableDishesCount, 4));

                for ($j = 0; $j < $branchOffersCount; $j++) {
                    if ($totalOffersCreated >= $maxOffers) {
                        break;
                    }

                    $template = $menuPool[$j];

                    // 🔮 حساب رقم الصورة ديناميكياً باستخدام الـ Modulus لضمان عدم الخروج عن النطاق المتاح
                    $totalBrandImages = $maxImagesPerBrand[$brandKey] ?? 1;
                    $imageNumber = ($j % $totalBrandImages) + 1;

                    // تحويل اسم البراند لشكل متناسق مع أسماء الملفات (مثال: burger king -> burger-king)
                    $imageName = Str::slug($brandKey) . $imageNumber . '.jpg';

                    $localImagePath = public_path('uploads/' . $imageName);

                    $dynamicImageUrl = 'uploads/' . $imageName;

                    $originalPrice = match ($vendorType) {
                        'restaurant' => rand(120, 250),
                        'cafe' => rand(90, 180),
                        'bakery' => rand(40, 120),
                        'hotel' => rand(150, 400),
                        default => rand(50, 200),
                    };
                    $discountPercentage = rand(15, 50) / 100;
                    $discountPrice = $originalPrice * (1 - $discountPercentage);


                    $dynamicTitle = $originalName . ' - ' . $template['title'];
                    $dynamicDescription = $template['description'] . ' Available now at ' . $originalName . '.';
                    $randomStatus = ($disabledCount < 5 && ($totalOffersCreated === 0 || rand(0, 1)))
                        ? 'disabled'
                        : 'active';

                    if ($randomStatus === 'disabled') {
                        $disabledCount++;
                    }
                    $expirationHours = match ($vendorType) {
                        'restaurant' => rand(12, 16),
                        'cafe' => rand(12, 18),
                        'bakery' => rand(16, 20),
                        'hotel' => rand(14, 18),
                        default => rand(20, 24),
                    };

                    offer::create([
                        'branch_id' => $branch->id,
                        'title' => $dynamicTitle,
                        'description' => $dynamicDescription,
                        'image' => $dynamicImageUrl, // 👈 هنا الصورة الديناميكية الصح المربوطة بالـ Title والـ Type
                        'quantity_available' => rand(1, 10),
                        'original_price' => $originalPrice,
                        'discount_price' => round($discountPrice, 2),
                        'expiration_time' => Carbon::parse('2026-06-07 10:00:00')->addHours($expirationHours),
                        'status' => $randomStatus,
                    ]);

                    $totalOffersCreated++;
                }
            }
        }
    }
}