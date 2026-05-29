<?php

namespace App\Http\Controllers;

use App\Models\order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\offer;
use App\Services\PaymentService;
use App\Models\customer;

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
                    $deliveryFees = max(15, round($distanceKm * 5, 2));
                }

                // 4. إنشاء الأوردر الأساسي
                $order = order::create([
                    'customer_id' => $customer->id,
                    'vendor_id' => $vendor->id,
                    'branch_id' => $branch->id, // ربط الأوردر بالفرع مباشرة
                    'order_status' => 'pending',
                    'delivery_type' => $request->delivery_type,
                    'delivery_address' => $request->delivery_address,
                    'delivery_fees' => $deliveryFees,
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
                        'price' => $itemPrice,
                    ]);
                }

                // 6. تحديث إجمالي المبلغ
                $order->update(['total_amount' => $total + $deliveryFees]);

                // 7. التعامل مع الدفع
                if ($request->payment_method === 'card') {
                    $paymentUrl = $this->paymentService->createCheckoutSession($order);
                    \App\Models\payment::create([
                        'order_id' => $order->id,
                        'transaction_id' => $this->paymentService->getLastSessionId(), // الـ ID اللي جاي من Stripe
                        'amount' => $order->total_amount,
                        'payment_status' => 'pending',
                        'payment_method' => 'card'
                    ]);
                    return response()->json(['payment_url' => $paymentUrl], 201);
                }

                return response()->json([
                    'message' => 'Order created successfully (Cash)',
                    'order' => $order->load('items')
                ], 201);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Failed!',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // 🟢 هنا السر! الـ 500 هتتحول لرسالة مقروءة هتقولهم بالملي إيه اللي ضرب جوه الكود
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

        // العميل يقدر يكنسل لو الطلب لسه مخلصش (pending أو processing)
        if (\in_array($order->order_status, ['pending', 'processing'])) {
            try {
                return DB::transaction(function () use ($order) {
                    $order->update(['order_status' => 'cancelled']);

                    // ترجيع الاستوك للمخزن
                    foreach ($order->items as $item) {
                        $item->offer->restoreStock($item->quantity);
                    }

                    return response()->json(['message' => 'Order cancelled and stock restored']);
                });
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error during cancellation'], 500);
            }
        }

        return response()->json(['message' => 'Cannot cancel this order'], 403);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function show($id)
    {
        $order = order::where('customer_id', auth()->id())
            ->with(['items.offer:id,title,price,image'])
            ->findOrFail($id);

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
        // 1. بنجيب كل الأوردرات اللي تخص الكاستمر ده بس
        // بنستخدم with عشان نجيب بيانات العروض معاها (Eager Loading)
        $orders = order::where('customer_id', auth()->id())
            ->with(['items.offer'])
            ->orderByDesc('created_at') // الأحدث يظهر في الأول
            ->get();

        // 2. بنرجع الأوردرات في JSON
        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'data' => $orders
        ]);
    }
}
