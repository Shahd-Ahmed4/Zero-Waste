<?php

namespace App\Http\Controllers;
use App\Models\vendor;
use Illuminate\Http\Request;
use Notification;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\payment;
use App\Models\order;
use Stripe\Webhook;
use UnexpectedValueException;
use Stripe\Exception\SignatureVerificationException;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    // 1. لما العميل يرجع للينك الـ Success في التطبيق
    public function paymentSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $this->updatePaymentAndOrder($session->id, 'completed');
                return response()->json(['status' => 'success', 'message' => 'Payment confirmed!']);
            }

            return response()->json(['message' => 'Payment not verified'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid Session'], 400);
        }
    }

    // 2. الـ Webhook لتأكيد الدفع في الخلفية (أمان زيادة)
    public function handleWebhook(Request $request)
    {
        $endpoint_secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->updatePaymentAndOrder($event->data->object->id, 'completed');
        }

        return response()->json(['status' => 'success']);
    }

    public function paymentCancel()
    {
        return response()->json(['message' => 'Payment Cancelled'], 200);
    }

    // 3. الفانكشن المشتركة لتحديث الداتا وإرسال الإشعارات
    private function updatePaymentAndOrder($sessionId, $status)
    {
        $payment = payment::where('transaction_id', $sessionId)->first();

        // نتأكد إننا محدثناش الأوردر ده قبل كدة (عشان ميبعتش إشعار مرتين)
        if ($payment && $payment->payment_status !== 'completed') {
            $payment->update(['payment_status' => $status]);

            $order = $payment->order;
            if ($order) {
                // تحديث حالة الأوردر لـ "قيد التحضير"
                $order->update(['order_status' => 'processing']);

                $this->sendOrderNotifications($order);
            }
        }
    }

    private function sendOrderNotifications($order)
    {
        // إشعار للكاستمر
        notification::create([
            'user_id' => $order->customer_id,
            'message' => "Payment Successful! Your order #{$order->id} is confirmed and being prepared.",
            'type' => 'order',
        ]);

        // إشعار للفيندور (مدير الفرع الرئيسي)
        if ($order->vendor_id) {
            $vendor = vendor::find($order->vendor_id);
            if ($vendor) {
                notification::create([
                    'user_id' => $vendor->user_id,
                    'message' => "New Paid Order! Order #{$order->id} is ready for you to prepare.",
                    'type' => 'order',
                ]);
            }
        }
    }
}