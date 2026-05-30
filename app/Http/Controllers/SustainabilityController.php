<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\order_item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SustainabilityController extends Controller
{
    // الثابت البيئي: الوجبة المتوسطة (نصف كيلو) تمنع 1.25 كجم من الكربون
    private $co2Factor = 1.25;

    /**
     * 1. الـ Admin Dashboard API
     */
    public function getAdminMetrics()
    {
        try {
            // إجمالي الوجبات المنقذة بالسيستم كله (الأوردرات الـ completed بس)
            $mealsSaved = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->sum('quantity');

            // الأرباح المستردة لكل التجار (إجمالي أسعار البيع بعد الخصم)
            $recoveredRevenue = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->selectRaw('SUM(price * quantity) as total')->value('total') ?? 0;

            // إجمالي توفير المستهلكين (الحسبة بقت مباشرة وسهلة من غير Join)
            $consumerSavings = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->selectRaw('SUM((original_price - price) * quantity) as savings')->value('savings') ?? 0;

            // منع انبعاثات الكربون
            $co2Prevented = $mealsSaved * $this->co2Factor;

            // بيانات الرسم البياني (Sustainability Chart) مجمعة بالشهور لآخر سنة
            $chartData = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->selectRaw('MONTHNAME(orders.order_date) as month, MONTH(orders.order_date) as month_num, SUM(order_items.quantity) as meals')
                ->groupBy('month','month_num')
                ->orderBy('month_num', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'meals_saved' => (int) $item->meals,
                        'co2_prevented' => $item->meals * $this->co2Factor
                    ];
                });

            return response()->json([
                'success' => true,
                'metrics' => [
                    'meals_saved' => (int) $mealsSaved,
                    'recovered_revenue' => round($recoveredRevenue, 2),
                    'consumer_savings' => round($consumerSavings, 2),
                    'co2_prevented_kg' => round($co2Prevented, 2),
                ],
                'chart_data' => $chartData
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 2. الـ Vendor Dashboard API
     */
    public function getVendorMetrics(Request $request)
    {
        try {
            // بنجيب الـ Vendor المرتبط بالمستخدم اللي عامل Login
            $vendorId = auth()->user()->vendor->id;

            // وجبات المحل ده بالذات اللي أنقذها
            $mealsSaved = order_item::whereHas('order', function ($q) use ($vendorId) {
                $q->where('order_status', 'completed')->where('vendor_id', $vendorId);
            })->sum('quantity');

            // الأرباح المستردة للمحل ده
            $recoveredRevenue = order_item::whereHas('order', function ($q) use ($vendorId) {
                $q->where('order_status', 'completed')->where('vendor_id', $vendorId);
            })->selectRaw('SUM(price * quantity) as total')->value('total') ?? 0;

            $co2Prevented = $mealsSaved * $this->co2Factor;

            return response()->json([
                'success' => true,
                'metrics' => [
                    'meals_saved' => (int) $mealsSaved,
                    'recovered_revenue' => round($recoveredRevenue, 2),
                    'co2_prevented_kg' => round($co2Prevented, 2),
                    'green_badge' => $mealsSaved >= 50 ? 'Eco-Friendly Partner' : 'Rising Sustainability Hero'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. الـ Customer Profile API
     */
    public function getCustomerMetrics()
    {
        try {
            // بنجيب الـ customer_id للزبون اللي عامل Login
            $customer = \App\Models\customer::where('user_id', auth()->id())->first();

            if (!$customer) {
                return response()->json(['message' => 'Customer profile not found'], 404);
            }

            // وجبات الزبون ده اللي اشتراها
            $mealsSaved = order_item::whereHas('order', function ($q) use ($customer) {
                $q->where('order_status', 'completed')->where('customer_id', $customer->id);
            })->sum('quantity');

            // الفلوس اللي الزبون وفرها (حسبة مباشرة بدون Join مع جدول العروض)
            $moneySaved = order_item::whereHas('order', function ($q) use ($customer) {
                $q->where('order_status', 'completed')->where('customer_id', $customer->id);
            })->selectRaw('SUM((original_price - price) * quantity) as savings')->value('savings') ?? 0;

            $co2Prevented = $mealsSaved * $this->co2Factor;

            return response()->json([
                'success' => true,
                'metrics' => [
                    'meals_saved' => (int) $mealsSaved,
                    'total_money_saved' => round($moneySaved, 2),
                    'co2_prevented_kg' => round($co2Prevented, 2),
                    'thank_you_message' => "Thank you! Your purchases have helped our planet by reducing harmful emissions."
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}