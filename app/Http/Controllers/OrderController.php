<?php

namespace App\Http\Controllers;

use App\Models\order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\offer;
use App\Services\PaymentService;
use App\Models\customer;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    protected PaymentService $paymentService;

    // بنحقن الـ PaymentService عشان نستخدمها في الدفع الإلكتروني
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function store(Request $request)
    {
        try {
            $customer = customer::where('user_id', auth()->id())->first();

            if (!$customer) {
                return response()->json(['message' => 'This user does not have a customer account.'], 404);
            }
            // 1. التأكد من صحة البيانات المرسلة من الفرونت إند
            $request->validate([
                'items' => 'required|array',
                'payment_method' => 'required|in:card,cash',
                'delivery_type' => 'required|in:pickup,delivery',
                'customer_lat' => 'required_if:delivery_type,delivery',
                'customer_long' => 'required_if:delivery_type,delivery',
                'payment_method_id' => 'required_if:payment_method,card',
            ]);

            // استخدام الـ Transaction عشان لو حصلت مشكلة في النص، مفيش داتا تضرب
            return DB::transaction(function () use ($request, $customer) {

                // 2. الوصول لأول عرض عشان نعرف الفرع والفيندور
                $firstOffer = offer::with('branch.vendor')->findOrFail($request->items[0]['offer_id']);
                $branch = $firstOffer->branch;
                $vendor = $branch->vendor;

                // 3. حساب مصاريف التوصيل بناءً على موقع الفرع (Branch)
                $deliveryFees = 0;
                if ($request->delivery_type === 'delivery') {
                    $distanceKm = $this->calculateDistance(
                        $request->customer_lat,
                        $request->customer_long,
                        $branch->lat,
                        $branch->long
                    );
                    // الحد الأدنى للتوصيل 15 جنيه، وكل كيلو بـ 5 جنيه
                    $deliveryFees = min(50, max(15, round($distanceKm * 3, 2)));
                }
                // توليد رقم الحجز الفريد
                do {
                    $reservationId = 'RES-' . strtoupper(Str::random(6));
                } while (\App\Models\order::where('reservation_id', $reservationId)->exists());

                // 4. إنشاء الأوردر الأساسي
                $order = order::create([
                    'reservation_id' => $reservationId, // 👈 ضيفي السطر ده هنا
                    'customer_id' => $customer->id,
                    'vendor_id' => $vendor->id,
                    'branch_id' => $branch->id, // ربط الأوردر بالفرع مباشرة
                    'order_status' => 'pending',
                    'delivery_type' => $request->delivery_type,
                    'delivery_address' => $request->delivery_address,
                    'delivery_fees' => $deliveryFees,
                    'commission_fee' => 0, // قيمة مبدئية
                    'payment_method' => $request->payment_method,
                    'total_amount' => 0,
                    'order_date' => now(),
                ]);

                // 5. إضافة الأصناف وخصمها من مخزن الفرع
                $total = 0;
                foreach ($request->items as $item) {
                    // عمل Lock على السطر في الداتابيز عشان نمنع تضارب الطلبات في نفس الوقت
                    $offer = offer::lockForUpdate()->find($item['offer_id']);

                    // خصم الكمية من الموديل (تأكدي من وجود ميثود reduceStock في موديل Offer)
                    $offer->reduceStock($item['quantity']);
                    $itemPrice = $offer->discount_price;

                    $total += ($itemPrice * $item['quantity']);

                    $order->items()->create([
                        'offer_id' => $offer->id,
                        'quantity' => $item['quantity'],
                        'original_price' => $offer->original_price, // <- السعر الأصلي سحبناه من العرض وتثبت هنا
                        'price' => $itemPrice,
                    ]);
                }
                // 🧮 6. حسبة العمولة بدقة (6% من إجمالي تمن الأكل الصافي)
                $customerCommission = round($total * 0.06, 2);
                // إجمالي المبلغ النهائي الشامل (الأكل + عمولتنا + الدليفري)
                $finalTotalAmount = $total + $customerCommission + $deliveryFees;

                $order->update([
                    'commission_fee' => $customerCommission, // 👈 حفظنا قيمة الـ 6% لوحدها بالفلس
                    'total_amount' => $finalTotalAmount
                ]);

                // 🎯 7. التعامل مع الدفع (التعديل الجوهري هنا)
                if ($request->payment_method === 'card') {
                    try {
                        // بننادي على Stripe عشان يعمل الـ السحب الفوري (PaymentIntent)
                        // ملحوظة: تأكدي من عمل الـ use لـ \Stripe\Stripe فوق أو سيبيها كدة
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

                        $charge = \Stripe\PaymentIntent::create([
                            'amount' => (int) ($order->total_amount * 100), // بالقروش
                            'currency' => 'egp',
                            'payment_method' => $request->payment_method_id,
                            'confirm' => true, // سحب لايف في نفس الثانية
                            'automatic_payment_methods' => [
                                'enabled' => true,
                                'allow_redirects' => 'never' // عشان يفضل العميل جوه شاشة الأبلكيشن
                            ],
                            'metadata' => [
                                'order_id' => $order->id,
                                'customer_id' => $customer->id,
                            ]
                        ]);

                        // ✅ لو الدفع تم بنجاح والفلوس اتسحبت فعلياً
                        if ($charge->status === 'succeeded') {
                            \App\Models\payment::create([
                                'order_id' => $order->id,
                                'transaction_id' => $charge->id, // الـ ID الحقيقي للعملية من Stripe
                                'amount' => $order->total_amount,
                                'payment_status' => 'completed',
                                'payment_method' => 'card'
                            ]);

                            // الأوردر مدفوع وجاهز.. يتحول فوراً لـ "قيد التحضير"
                            $order->update(['order_status' => 'processing']);

                            // إرسال الإشعارات الفورية
                            \App\Models\notification::create([
                                'user_id' => $customer->user_id,
                                'message' => "Payment successful! Order #{$order->id} is being prepared.",
                                'type' => 'order',
                            ]);

                            \App\Models\notification::create([
                                'user_id' => $vendor->user_id,
                                'message' => "New paid order #{$order->id}! Please prepare it.",
                                'type' => 'order',
                            ]);

                            return response()->json([
                                'success' => true,
                                'message' => 'Order created and payment successful!',
                                'order' => $order->refresh()->load('items')
                            ], 201);
                        } else {
                            // لو الـ status رجع أي حاجة مش نجاح (رمي Exception عشان الـ Transaction تقلب)
                            throw new \Exception('Stripe payment status: ' . $charge->status);
                        }

                    } catch (\Exception $e) {
                        // 🟢 هنا حماية الداتابيز: لو كارت العميل اترفض، الـ Exception ده هيطير 
                        // والـ DB::transaction هتعمل أوتوماتيكياً Rollback وتمسح الأوردر وترجع الـ Stock
                        throw new \Exception('Payment process failed: ' . $e->getMessage());
                    }
                }

                // 🔔 إشعار للعميل في حالة الـ Cash إن الأوردر نجح ومستني التاجر
                \App\Models\notification::create([
                    'user_id' => $customer->user_id,
                    'message' => "Your order #{$order->id} has been placed successfully and is waiting for vendor approval.",
                    'type' => 'order',
                ]);

                // 🔔 إشعار للفيندور (التاجر) إن جاله أوردر جديد كاش ومحتاج يدخل يوافق عليه
                \App\Models\notification::create([
                    'user_id' => $vendor->user_id,
                    'message' => "You have received a new order #{$order->id}! Please review and accept it.",
                    'type' => 'order',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully (Cash)',
                    'order' => $order->refresh()->load('items')
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Failed!',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // الـ 500 المقروءة والجميلة اللي بتشرح السبب لو الفيزا اترفضت أو الكود ضرب
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while placing the order!',
                'error_debug' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        // 1. الفاليجيشن بتاعك مع إضافة الحالات الجديدة
        $request->validate([
            'status' => 'required|in:processing,completed,cancelled,delivered'
        ]);

        $vendor = auth()->user()->vendor; // الفيندور اللي عامل login

        // 2. نجيب الأوردر ونتأكد إنه يخص الفيندور ده (أمان)
        $order = order::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        // 3. حماية أوردرات الفيزا (ممنوع البدء قبل الدفع)
        if ($order->payment_method === 'card' && $order->order_status === 'pending' && $request->status === 'processing') {
            return response()->json(['message' => 'This order is not paid yet!'], 403);
        }

        // 4. المنطق بتاعك (الفرق بين الاستلام والشحن)
        if ($request->status === 'completed') {
            // لو استلام من الفرع يبقى خلاص خلص، لو شحن يبقى "في الطريق"
            $order->order_status = ($order->delivery_type === 'pickup') ? 'completed' : 'in_transit';
        } elseif ($request->status === 'delivered') {
            // حالة الـ delivered بنستخدمها بس في الشحن (delivery)
            // وبتحول حالة الأوردر لـ completed عشان يدخل في الأرباح
            if ($order->delivery_type === 'delivery') {
                $order->order_status = 'completed';
            } else {
                return response()->json(['message' => 'Pickup orders cannot be set to delivered'], 400);
            }

        } elseif ($request->status === 'cancelled') {
            // لو الأوردر اتكنسل، نرجع البضاعة للمخزن (الـ Logic بتاعك سليم)
            foreach ($order->items as $item) {
                $item->offer->restoreStock($item->quantity);
            }
            $order->order_status = 'cancelled';

        } else {
            $order->order_status = $request->status;
        }

        $order->save();

        // 5. إرسال الإشعار (مهم جداً لتجربة المستخدم)
        $this->sendNotificationToCustomer($order);

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order
        ]);
    }

    // فانكشن مساعدة للإشعارات عشان الكود يفضل نظيف
    private function sendNotificationToCustomer($order)
    {
        $messages = [
            'processing' => "The vendor is preparing your order #{$order->id}.",
            'in_transit' => "Your order #{$order->id} is on its way to you!",
            'completed' => "Order #{$order->id} completed. Enjoy!",
            'cancelled' => "Your order #{$order->id} has been cancelled by the vendor."
        ];

        if (isset($messages[$order->order_status])) {
            \App\Models\notification::create([
                'user_id' => $order->customer_id,
                'message' => $messages[$order->order_status],
                'type' => 'order',
            ]);
        }
    }
    public function calculateFee(Request $request)
    {
        $request->validate([
            'customer_lat' => 'required|numeric',
            'customer_long' => 'required|numeric',
            'offer_id' => 'required|exists:offers,id',
        ]);

        // سيبناها offer سمول زي ما هيّ في مشروعك
        $offer = offer::with('branch')->findOrFail($request->offer_id);
        $branch = $offer->branch;

        // الدالة دي بتنادي على الـ calculateDistance بتاعتك اللي تحت علطول
        $distanceKm = $this->calculateDistance(
            $request->customer_lat,
            $request->customer_long,
            $branch->lat,
            $branch->long
        );
        $deliveryFee = min(50, max(15, round($distanceKm * 3, 2)));

        return response()->json([
            'delivery_fee' => $deliveryFee,
            'distance_km' => round($distanceKm, 2),
        ]);
    }

    // معادلة هافرساين لحساب المسافة بين نقطتين
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        return rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
    }

    /**
     * Display the specified resource.
     */
    public function cancel($id)
    {
        $order = order::with('items.offer')->where('customer_id', auth()->id())->findOrFail($id);

        if (in_array($order->order_status, ['pending', 'processing'])) {
            return DB::transaction(function () use ($order) {
                $order->update(['order_status' => 'cancelled']);

                foreach ($order->items as $item) {
                    // جلب العرض المقفول للتعديل لضمان عدم حدوث تضارب
                    $offer = offer::lockForUpdate()->find($item->offer_id);
                    if ($offer) {
                        $offer->restoreStock($item->quantity);
                    }
                }

                return response()->json(['message' => 'Order cancelled and stock restored']);
            });
        }

        return response()->json(['message' => 'Cannot cancel this order'], 403);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function show($id)
    {
        $customer = \App\Models\customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found.'
            ], 404);
        }
        // 2. بنجيب الأوردر بناءً على الـ customer_id الصح بتاعه
        $order = \App\Models\order::where('customer_id', $customer->id)
            ->with(['items.offer:id,title,original_price,discount_price,image']) // ضيفنا الأسعار عشان الفرونت يعرض الحسبة صح
            ->findOrFail($id);

        // لارافيل أوتوماتيك هيبعت الـ commission_fee والـ delivery_fees جوه الـ order
        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function index()
    {
        try {
            // 1. بنجيب حساب الكاستمر المرتبط باليوزر اللي عامل Login حالياً
            $customer = customer::where('user_id', auth()->id())->first();

            // لو لسبب ما اليوزر ده ملوش حساب كاستمر
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer profile not found.'
                ], 404);
            }

            // 2. بنجيب الأوردرات باستخدام الـ customer->id الصح بتاعه من جدول الكاستمرز
            $orders = order::where('customer_id', $customer->id)
                ->with(['items.offer'])
                ->orderByDesc('created_at') // الأحدث يظهر في الأول
                ->get();

            // 3. بنرجع الأوردرات في JSON والـ count هنا هيطلع رقمها الحقيقي
            return response()->json([
                'success' => true,
                'count' => $orders->count(),
                'data' => $orders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error_debug' => $e->getMessage()
            ], 500);
        }
    }
}
