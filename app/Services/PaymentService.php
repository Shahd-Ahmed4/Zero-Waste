<?php

namespace App\Services;

use App\Models\order;
use Session;
use Stripe\Stripe;

class PaymentService
{
    private $lastSessionId;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        // تعريف مفتاح السر من ملف الـ .env
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCheckoutSession(order $order)
    {
        // تجهيز بيانات الدفع بناءً على الأوردر الشامل لمصاريف التوصيل
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'egp',
                        'product_data' => [
                            'name' => "Order #" . $order->id,
                            'description' => "Payment for your food order from Branch ID: " . $order->branch_id,
                        ],
                        'unit_amount' => (int) ($order->total_amount * 100), // Stripe بيحسب بالقروش
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            // الروابط اللي هيرجع ليها العميل (Success & Cancel)
            'success_url' => url('/api/payment/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/api/payment/cancel'),
            'metadata' => [
                'order_id' => $order->id
            ]
        ]);
        $this->lastSessionId = $session->id;

        return $session->url;
    }
    public function getLastSessionId()
    {
        return $this->lastSessionId;
    }
}
