<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\order;
use App\Models\order_item; // مكتوب سمول حسب اسم الموديل بتاعك
use App\Models\payment;
use App\Models\review; // استدعاء موديل التقييمات
use App\Models\customer;
use App\Models\offer;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run()
    {
        // بنجيب كل العملاء والعروض المتاحة في قاعدة البيانات
        $customers = customer::all();
        $offers = offer::with('branch.vendor')->get();

        // لو مفيش عملاء أو عروض بنوقف السيدر عشان منضربش إيرور
        if ($customers->isEmpty() || $offers->isEmpty()) {
            return;
        }

        // قوالب تعليقات واقعية بالإنجليزية مقسمة حسب نوع الـ Vendor عشان الريفيو يلوق على الأكل
        $reviewComments = [
            'restaurant' => [
                'Amazing food quality! The grilled chicken was still warm and delicious.',
                'Great value for money, the portion size was huge and tasted fresh.',
                'Excellent meal, clean packaging, and very fast pickup process.',
                'Super happy to save this surplus food, tasted like premium catering!'
            ],
            'cafe' => [
                'The croissants were amazing, perfect evening snack with my coffee.',
                'Great discount on the donuts box! Delicious and still soft.',
                'The sandwiches were very fresh and clean. Highly recommended!',
                'Loved the bakery box, great initiative to reduce food waste.'
            ],
            'supermarket' => [
                'The vegetable box was heavy and full of fresh tomatoes and cucumbers.',
                'Excellent fruit basket, perfectly ripe and ready for making juice.',
                'Very good dairy bundle, saved a lot of money tonight!',
                'Smooth experience and the grocery items were in perfect condition.'
            ],
            'bakery' => [
                'Baguettes were still crunchy and perfect for dinner. Thank you!',
                'The fudge cake was heavenly! Such a steal for this price.',
                'Amazing mix of eastern sweets, fresh and high quality.'
            ],
            'hotel' => [
                '5-star buffet quality at a fraction of the cost! Outstanding.',
                'The high tea box was super luxury, loved the savory mini sandwiches.',
                'The sushi platter prepared by the chef was incredibly fresh and tasty.'
            ]
        ];

        // هنعمل حوالي 40 أوردر منوعين على العروض الـ 25 اللي عندنا
        for ($i = 0; $i < 40; $i++) {
            
            // اختيار عشوائي لعميل وعرض
            $customer = $customers->random();
            $offer = $offers->random();
            
            // سحب بيانات الفرع والتاجر مباشرة من العرض لضمان توافق البيانات تماماً
            $branch = $offer->branch;
            $vendor = $branch->vendor;

            if (!$vendor) {
                continue;
            }

            // تحديد طريقة الدفع ونوع التوصيل بشكل عشوائي
            $deliveryType = fake()->randomElement(['pickup', 'delivery']);
            $paymentMethod = fake()->randomElement(['card', 'cash']);
            
            // مصاريف الشحن: لو pickup بتبقى 0، لو دليفري بنحط رقم عشوائي
            $deliveryFees = $deliveryType === 'delivery' ? fake()->randomElement([15.00, 20.00, 25.00]) : 0.00;
            
            // تحديد كمية عشوائية مطلوبة بشرط متعديش المتاح في العرض
            $quantity = rand(1, min(2, $offer->quantity_available));
            
            // حساب إجمالي المبلغ بدقة: (سعر الخصم × الكمية) + مصاريف الشحن
            $itemPrice = $offer->discount_price;
            $totalAmount = ($itemPrice * $quantity) + $deliveryFees;

            // تحديد حالة الأوردر بشكل منوع وواقعي (أغلبها مكتملة عشان تقارير الأرباح)
            $orderStatus = fake()->randomElement([
                'completed', 'completed', 'completed', 'delivered', 
                'processing', 'pending', 'cancelled'
            ]);

            // توزيع تاريخ الأوردرات على مدار الـ 30 يوم اللي فاتوا
            $orderDate = Carbon::now()->subDays(rand(0, 30))->subHours(rand(1, 23));

            // 1. إنشاء الأوردر الأساسي
            $order = order::create([
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'branch_id' => $branch->id,
                'order_status' => $orderStatus,
                'delivery_type' => $deliveryType,
                'delivery_address' => $deliveryType === 'delivery' ? $customer->user->address : null,
                'delivery_fees' => $deliveryFees,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'order_date' => $orderDate,
            ]);

            // 2. إنشاء تفاصيل الأوردر (Order Items) مرتبطة بالعرض وسعره
            order_item::create([
                'order_id' => $order->id,
                'offer_id' => $offer->id,
                'price' => $itemPrice,
                'quantity' => $quantity,
            ]);

            // تحديد حالة الدفع بناءً على حالة الأوردر وطريقة الدفع
            $paymentStatus = 'pending';
            if (in_array($orderStatus, ['completed', 'delivered'])) {
                $paymentStatus = 'completed';
            } elseif ($orderStatus === 'cancelled') {
                $paymentStatus = $paymentMethod === 'card' ? 'refunded' : 'failed';
            }

            // 3. إنشاء سجل الدفع (Payment) المرتبط بالأوردر
            payment::create([
                'order_id' => $order->id,
                'transaction_id' => $paymentMethod === 'card' ? 'TXN_' . strtoupper(str()->random(12)) : null,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => $totalAmount,
                'payment_details' => $paymentMethod === 'card' ? [
                    'gateway' => 'stripe',
                    'currency' => 'EGP',
                    'card_type' => fake()->randomElement(['Visa', 'MasterCard'])
                ] : null,
                'payment_date' => $paymentStatus === 'completed' ? $orderDate : null,
            ]);

            // 4. إنشاء التقييم (Review) - هنعمله فقط للأوردرات الناجحة والمكتملة عشان الواقعية
            if (in_array($orderStatus, ['completed', 'delivered'])) {
                
                // تحديد نوع الـ Vendor لنداء الكومنت المناسب له
                $vendorType = strtolower(trim($vendor->vendor_type));
                if ($vendorType === 'restaurants') { $vendorType = 'restaurant'; }
                if ($vendorType === 'bakeries') { $vendorType = 'bakery'; }
                if ($vendorType === 'hotels') { $vendorType = 'hotel'; }

                // لو النوع مش متطابق بنخليه ريستورانت كـ افتراضي لعدم حدوث إيرور
                if (!array_key_exists($vendorType, $reviewComments)) {
                    $vendorType = 'restaurant';
                }

                // اختيار كومنت عشوائي مناسب لنوع المحل
                $randomComment = fake()->randomElement($reviewComments[$vendorType]);

                review::create([
                    'customer_id' => $customer->id,
                    'offer_id' => $offer->id,
                    // بنخلي التقييمات عالية وواقعية (4 أو 5 نجوم) عشان شكل البروجكت يكون حلو
                    'rating' => fake()->randomElement([4, 5, 5, 5]), 
                    'comment' => $randomComment,
                    'is_visible' => true,
                    'created_at' => $orderDate->addHours(rand(1, 4)), // العميل بيقيم بعد كام ساعة من الأوردر
                ]);
            }
        }
    }
}