<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\offer;
use App\Models\order;
use App\Models\order_item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class AdminDashboardController extends Controller
{
    /**
     * 1. الكروت الإحصائية والمالية (Admin Cards)
     */
    public function getOverviewStats()
    {
        // بنحسب إجمالي أسعار المنتجات الصافية (من غير دليفري) من جدول الـ order_items للأوردرات الـ completed
        $totalSales = order_item::whereHas('order', function ($query) {
            $query->where('order_status', 'completed');
        })
            ->select(DB::raw('SUM(price * quantity) as total_base_sales'))
            ->first();

        // ده إجمالي قيمة المنتجات الصافية في السيستم كله (المتغير $total اللي كان في الأوردر)
        $baseSales = (float) ($totalSales->total_base_sales ?? 0);

        // حساب العمولات في الطاير بناءً على قيمة المنتجات الصافية
        $customerCommissions = round($baseSales * 0.06, 2); // الـ 6% اللي زادت على الكاستمر
        $vendorCommissions = round($baseSales * 0.12, 2); // الـ 12% اللي هتتخصم من الفيندور
        $platformPureProfit = $customerCommissions + $vendorCommissions; // الـ 18% الصافية لكم

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => User::where('role', 'customer')->count(),
                'total_vendors' => User::where('role', 'vendor')->count(),
                'total_orders' => order::count(),
                'total_active_offers' => offer::where('status', 'active')->count(),
                'financials' => [
                    'total_market_sales' => $baseSales, // إجمالي المبيعات قبل أي عمولات أو دليفري
                    'customer_fees_6_pct' => $customerCommissions,
                    'vendor_fees_12_pct' => $vendorCommissions,
                    'net_platform_profit' => $platformPureProfit, // كارت صافي أرباح المنصة
                    'currency' => 'EGP'
                ]
            ]
        ]);
    }

    /**
     * 2. رسم بياني لأرباح المنصة بالشهور (Admin Line Chart)
     */
    public function getMonthlyEarningsChart()
    {
        // بنجمع مبيعات المنتجات الشهرية من جدول الـ order_items بربطه مع جدول الأوردرات
        $monthlyData = order_item::whereHas('order', function ($query) {
            $query->where('order_status', 'completed');
        })
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                DB::raw('MONTH(orders.order_date) as month'),
                DB::raw('SUM(order_items.price * order_items.quantity) as monthly_base_sales')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // تحويل الداتا لنسبة أرباح المنصة الصافية (18% من مبيعات كل شهر)
        $chartData = $monthlyData->map(function ($item) {
            $sales = (float) $item->monthly_base_sales;
            $platformProfit = $sales * 0.18; // مجموع العمولتين (6% + 12%)

            return [
                'month' => date("F", mktime(0, 0, 0, $item->month, 10)), // بيحول رقم الشهر لـ اسم
                'pure_profit' => round($platformProfit, 2)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }

    public function getRecentActivity()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    // آخر 5 يوزرز سجلوا
                    'latest_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']),

                    // آخر 5 عروض مع اسم المحل - تم تصليح الـ select وتأمين الـ Keys
                    'latest_offers' => offer::latest()->take(5)->get(['id', 'title', 'status', 'created_at']),
                ]
            ]);
        } catch (Exception $e) {
            // 🔥 لو الكود ضرب لأي سبب (مثلاً اسم كولوم غلط)، السيرفر هيرجع للبنت رسالة واضحة فيها المشكلة بدل الـ 500 العمياء
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}