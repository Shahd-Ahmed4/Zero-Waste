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
    protected PaymentService $paymentService;
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
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.offer_id' => 'required|integer|exists:offers,id', 
                'items.*.quantity' => 'required|integer|min:1',
                'payment_method' => 'required|in:card,cash',
                'delivery_type' => 'required|in:pickup,delivery',
                'customer_lat' => 'required_if:delivery_type,delivery',
                'customer_long' => 'required_if:delivery_type,delivery',
                'payment_method_id' => 'required_if:payment_method,card',
            ]);
            $firstOffer = offer::with('branch.vendor')->findOrFail($request->items[0]['offer_id']);
            $branch = $firstOffer->branch;
            $vendor = $branch->vendor;
            $vendorId = $vendor->id;
            foreach ($request->items as $item) {
                $offerCheck = offer::with('branch.vendor')->findOrFail($item['offer_id']);
                if ($offerCheck->branch->vendor_id !== $vendorId) {
                    throw new \Exception('All items must be from the same vendor!');
                }
                if ($offerCheck->branch_id !== $branch->id) {
                    throw new \Exception('All items must be from the same branch!');
                }
            }
            return DB::transaction(function () use ($request, $customer, $branch, $vendor) {
                $deliveryFees = 0;
                if ($request->delivery_type === 'delivery') {
                    $distanceKm = $this->calculateDistance(
                        $request->customer_lat,
                        $request->customer_long,
                        $branch->lat,
                        $branch->long
                    );
                    $deliveryFees = min(50, max(15, round($distanceKm * 3, 2)));
                }
                do {
                    $reservationId = 'RES-' . strtoupper(Str::random(6));
                } while (\App\Models\order::where('reservation_id', $reservationId)->exists());
                $order = order::create([
                    'reservation_id' => $reservationId, 
                    'customer_id' => $customer->id,
                    'vendor_id' => $vendor->id,
                    'branch_id' => $branch->id, 
                    'order_status' => 'pending',
                    'delivery_type' => $request->delivery_type,
                    'delivery_address' => $request->delivery_address,
                    'delivery_fees' => $deliveryFees,
                    'commission_fee' => 0, 
                    'payment_method' => $request->payment_method,
                    'total_amount' => 0,
                    'order_date' => now(),
                ]);
                $total = 0;
                foreach ($request->items as $item) {
                    $offer = offer::lockForUpdate()->findOrFail($item['offer_id']);
                    if ($offer->quantity_available < $item['quantity']) {
                        throw new \Exception("Insufficient stock for offer: {$offer->title}");
                    }
                    $offer->decrement('quantity_available', $item['quantity']);
                    $offer->refresh();
                    $itemPrice = $offer->discount_price;
                    $total += ($itemPrice * $item['quantity']);
                    $order->items()->create([
                        'offer_id' => $offer->id,
                        'quantity' => $item['quantity'],
                        'original_price' => $offer->original_price, 
                        'price' => $itemPrice,
                    ]);
                }
                $customerCommission = round($total * 0.06, 2);
                $finalTotalAmount = $total + $customerCommission + $deliveryFees;
                $order->update([
                    'commission_fee' => $customerCommission, 
                    'total_amount' => $finalTotalAmount
                ]);
                if ($request->payment_method === 'card') {
                    try {
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                        $charge = \Stripe\PaymentIntent::create([
                            'amount' => (int) ($order->total_amount * 100), 
                            'currency' => 'egp',
                            'payment_method' => $request->payment_method_id,
                            'confirm' => true, 
                            'automatic_payment_methods' => [
                                'enabled' => true,
                                'allow_redirects' => 'never' 
                            ],
                            'metadata' => [
                                'order_id' => $order->id,
                                'customer_id' => $customer->id,
                            ]
                        ]);
                        if ($charge->status === 'succeeded') {
                            \App\Models\payment::create([
                                'order_id' => $order->id,
                                'transaction_id' => $charge->id, 
                                'amount' => $order->total_amount,
                                'payment_status' => 'completed',
                                'payment_method' => 'card'
                            ]);
                            $order->update(['order_status' => 'processing']);
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
                            
                            throw new \Exception('Stripe payment status: ' . $charge->status);
                        }

                    } catch (\Exception $e) {
                        
                        throw new \Exception('Payment process failed: ' . $e->getMessage());
                    }
                }

                
                \App\Models\notification::create([
                    'user_id' => $customer->user_id,
                    'message' => "Your order #{$order->id} has been placed successfully and is waiting for vendor approval.",
                    'type' => 'order',
                ]);

                
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
        
        $request->validate([
            'status' => 'required|in:processing,completed,cancelled,delivered'
        ]);

        $vendor = auth()->user()->vendor; 

       
        $order = order::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        
        if ($order->payment_method === 'card' && $order->order_status === 'pending' && $request->status === 'processing') {
            return response()->json(['message' => 'This order is not paid yet!'], 403);
        }

        
        if ($request->status === 'completed') {
           
            $order->order_status = ($order->delivery_type === 'pickup') ? 'completed' : 'in_transit';
        } elseif ($request->status === 'delivered') {
            
            if ($order->delivery_type === 'delivery') {
                $order->order_status = 'completed';
            } else {
                return response()->json(['message' => 'Pickup orders cannot be set to delivered'], 400);
            }

        } elseif ($request->status === 'cancelled') {
            
            foreach ($order->items as $item) {
                $item->offer->restoreStock($item->quantity);
            }
            $order->order_status = 'cancelled';

        } else {
            $order->order_status = $request->status;
        }

        $order->save();

        
        $this->sendNotificationToCustomer($order);

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order
        ]);
    }

    
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
        $customer = customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $order = order::with('items.offer', 'payment')
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        if (in_array($order->order_status, ['pending', 'processing'])) {
            return DB::transaction(function () use ($order, $customer) {
                $order->update(['order_status' => 'cancelled']);

                // 🔄 رجوع الستوك
                foreach ($order->items as $item) {
                    $offer = offer::lockForUpdate()->find($item->offer_id);
                    if ($offer) {
                        $offer->restoreStock($item->quantity);
                    }
                }

                // 💳 لو الدفع كان بكارت، ارجع الفلوس
                if ($order->payment_method === 'card' && $order->payment) {
                    try {
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

                        \Stripe\Refund::create([
                            'payment_intent' => $order->payment->transaction_id,
                        ]);

                        // تحديث حالة الـ payment
                        $order->payment->update(['payment_status' => 'refunded']);

                    } catch (\Exception $e) {
                        // لو الـ refund فشل، متكملش الكانسل
                        throw new \Exception('Refund failed: ' . $e->getMessage());
                    }
                }

                // 🔔 إشعار للكاستومر
                $message = $order->payment_method === 'card'
                    ? "Order #{$order->id} cancelled and your money has been refunded."
                    : "Order #{$order->id} has been cancelled.";

                \App\Models\notification::create([
                    'user_id' => $customer->user_id,
                    'message' => $message,
                    'type' => 'order',
                ]);

                return response()->json([
                    'message' => 'Order cancelled and stock restored',
                    'refund' => $order->payment_method === 'card' ? 'Refund initiated' : 'No refund needed (cash)'
                ]);
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
