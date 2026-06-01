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
use Illuminate\Support\Str;

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

        // قوالب تعليبات واقعية بالإنجليزية مقسمة حسب نوع الـ Vendor عشان الريفيو يلوق على الأكل
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

        // رفع عدد الأوردرات لـ 1000 أوردر عشان الـ Charts تملى العين في المناقشة
        $totalOrders = 1000;

        for ($i = 0; $i < $totalOrders; $i++) {

            // اختيار عشوائي لعميل وعرض من العروض الحالية الممررة
            $customer = $customers->random();
            $offer = $offers->random();

            // سحب بيانات الفرع والتاجر مباشرة من العرض لضمان توافق البيانات تماماً
            $branch = $offer->branch;
            $vendor = $branch->vendor;

            if (!$vendor) {
                continue;
            }

            // تحديد طريقة الدفع ونوع التوصيل بشكل عشوائي ومتنوع كما في الكود السابق
            $deliveryType = fake()->randomElement(['pickup', 'delivery']);
            $paymentMethod = fake()->randomElement(['card', 'cash']);

            // مصاريف الشحن: لو pickup بتبقى 0، لو دليفري بنحط رقم عشوائي
            $deliveryFees = $deliveryType === 'delivery' ? fake()->randomElement([15.00, 20.00, 25.00]) : 0.00;

            // حتة الـ reservation_id تظهر فقط لو الزبون اختار pickup وتكون متنوعة ومختلفة لكل أوردر
            $reservationId = $deliveryType === 'pickup' ? 'RES-' . strtoupper(Str::random(6)) : null;

            // تحديد كمية عشوائية مطلوبة بشرط متعديش المتاح في العرض
            $quantity = rand(1, min(2, $offer->quantity_available));

            // حساب إجمالي المبلغ بدقة: (سعر الخصم × الكمية) + مصاريف الشحن
            $itemPrice = $offer->discount_price;
            $totalAmount = ($itemPrice * $quantity) + $deliveryFees;

            // حساب الـ commission_fee بنسبة ثابتة 6% من إجمالي الأوردر بالملّي
            $commissionFee = round($totalAmount * 0.06, 2);

            // تحديد حالة الأوردر بشكل منوع وواقعي (أغلبها مكتملة عشان تقارير الأرباح)
            $orderStatus = fake()->randomElement([
                'completed',
                'completed',
                'completed',
                'delivered',
                'processing',
                'pending',
                'cancelled'
            ]);

            // التعديل الذكي للتواريخ: بنوزعهم على الـ 12 شهر بالتناوب
            $targetMonth = ($i % 12) + 1;

            // بنلعب في الأيام والساعات بشكل عشوائي تماماً عشان نكسر التساوي والخط يطلع متموج
            $randomDay = rand(1, 28);
            $randomHour = rand(1, 23);

            $orderDate = Carbon::now()
                ->setMonth($targetMonth)
                ->setDay($randomDay)
                ->subHours($randomHour)
                ->subMinutes(rand(1, 59));

            // بنخلي 20% من الأوردرات تروح لشهور عشوائية تماماً عشان نلغى التساوي في الجراف ويطلع طالع ونازل
            if (rand(1, 5) === 1) {
                $orderDate->setMonth(rand(1, 12));
            }

            // لضمان عدم إنشاء أوردرات في شهور مستقبيلية بالنسبة للسنة الحالية
            if ($orderDate->isFuture()) {
                $orderDate = $orderDate->subYear();
            }

            // 1. إنشاء الأوردر الأساسي مع إضافة الـ commission_fee والـ reservation_id والتواريخ الديناميكية
            $order = order::create([
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'branch_id' => $branch->id,
                'order_status' => $orderStatus,
                'delivery_type' => $deliveryType,
                'delivery_address' => $deliveryType === 'delivery' ? ($customer->user->address ?? fake()->address()) : null,
                'delivery_fees' => $deliveryFees,
                'commission_fee' => $commissionFee,
                'reservation_id' => $reservationId,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'order_date' => $orderDate,
            ]);

            // 2. إنشاء تفاصيل الأوردر (Order Items) مرتبطة بالعرض وسعره
            order_item::create([
                'order_id' => $order->id,
                'offer_id' => $offer->id,
                'price' => $itemPrice,
                'original_price' => $offer->original_price,
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
                'transaction_id' => $paymentMethod === 'card' ? 'TXN_' . strtoupper(Str::random(12)) : null,
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
        }

        // طلبك: زيادة عدد التقييمات بشكل مكثف وكبير جداً على كل العروض مع الحفاظ على التنوع
        foreach ($offers as $currentOffer) {
            // سحب الـ vendor ونوعه عشان الكومنتات تطابق نوع المحل
            $vendor = $currentOffer->branch->vendor;
            if (!$vendor) {
                continue;
            }

            $vendorType = strtolower(trim($vendor->vendor_type));
            if ($vendorType === 'restaurants') {
                $vendorType = 'restaurant';
            }
            if ($vendorType === 'bakeries') {
                $vendorType = 'bakery';
            }
            if ($vendorType === 'hotels') {
                $vendorType = 'hotel';
            }

            if (!array_key_exists($vendorType, $reviewComments)) {
                $vendorType = 'restaurant';
            }

            // طلبك بالتحديد: عمل من 10 إلى 25 تقييمًا مكثفًا لكل عرض ليكون المشروع حيويًا للغاية
            $reviewsCount = rand(10, 25);

            for ($r = 0; $r < $reviewsCount; $r++) {
                $randomCustomer = $customers->random();
                $randomComment = fake()->randomElement($reviewComments[$vendorType]);

                // تحديد تاريخ عشوائي للريفيو في نفس السنة
                $reviewDate = Carbon::now()->subMonths(rand(0, 11))->subDays(rand(1, 28));

                review::create([
                    'customer_id' => $randomCustomer->id,
                    'offer_id' => $currentOffer->id,
                    'rating' => fake()->randomElement([3, 4, 5, 5, 5]), // تنويع النجوم مع الحفاظ على الأغلبية ممتازة
                    'comment' => $randomComment,
                    'is_visible' => fake()->randomElement([true, true, true, false]), // طلبك: التنويع بين الـ visible والـ invisible
                    'created_at' => $reviewDate,
                    'updated_at' => $reviewDate,
                ]);
            }
        }
    }
}