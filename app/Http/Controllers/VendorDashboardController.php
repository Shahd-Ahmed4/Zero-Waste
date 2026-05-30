<?php

namespace App\Http\Controllers;
use App\Models\order_item;
use App\Models\offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorDashboardController extends Controller
{
    /**
     * دالة مساعدة سريعة عشان نجيب الـ Vendor ID بتاع المستخدم الحالي
     * بناءً على علاقة الـ User بموديل الـ Vendor عندك
     */
    private function getVendorId()
    {
        return auth()->user()->vendor->id;
    }

    /**
     * 1. الكروت الإحصائية والمالية الصافية للتاجر (Vendor Cards)
     */
    public function getOverviewStats()
    {
        $vendorId = $this->getVendorId();

        // حساب إجمالي المبيعات الإجمالية للأوردرات الـ Completed الخاصة بهذا التاجر فقط
        $report = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->whereHas('order', function ($query) {
                $query->where('order_status', 'completed');
            })
            ->select(
                DB::raw('SUM(price * quantity) as total_gross_revenue'),
                DB::raw('SUM(quantity) as total_units_sold'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders')
            )
            ->first();

        $grossRevenue = (float) ($report->total_gross_revenue ?? 0);

        // خصم عمولة المنصة (12%) وحساب الصافي اللي هيدخل جيب التاجر
        $platformCommission = $grossRevenue * 0.12;
        $netVendorRevenue = $grossRevenue - $platformCommission;

        // حساب عدد العروض النشطة حالياً للمحل ده بس
        $activeOffersCount = offer::whereHas('branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->where('status', 'active')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'active_offers' => $activeOffersCount,
                'total_orders' => (int) ($report->total_orders ?? 0),
                'total_units_sold' => (int) ($report->total_units_sold ?? 0),
                'financials' => [
                    'gross_revenue' => $grossRevenue,       // الإجمالي قبل الخصم
                    'platform_deduction' => $platformCommission, // الـ 12% اللي المنصة خدتها
                    'net_revenue' => round($netVendorRevenue, 2), // صافي ربح التاجر (الكارت الرئيسي)
                    'currency' => 'EGP'
                ]
            ]
        ]);
    }

    /**
     * 2. رسم بياني لمبيعات التاجر الصافية بالشهور (Vendor Line Chart)
     */
    public function getMonthlySalesChart()
    {
        $vendorId = $this->getVendorId();

        $monthlyData = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->whereHas('order', function ($query) {
                $query->where('order_status', 'completed');
            })
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                DB::raw('MONTH(orders.order_date) as month'),
                DB::raw('SUM(order_items.price * order_items.quantity) as monthly_gross')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // تحويل المبيعات لصافي أرباح بالشهور بعد خصم الـ 12%
        $chartData = $monthlyData->map(function ($item) {
            $gross = (float) $item->monthly_gross;
            $net = $gross * 0.88; // الـ 88% المتبقية للتاجر بعد خصم الـ 12%
            return [
                'month' => date("F", mktime(0, 0, 0, $item->month, 10)), // اسم الشهر
                'net_sales' => round($net, 2)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }

    //4. إحصائية الأكثر مبيعاً (Top 5) - (تم نقلها من الـ order item)
    public function topSelling()
    {
        $vendorId = $this->getVendorId();

        $topOffers = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->select('offer_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('offer_id')
            ->orderByDesc('total_sold')
            ->with(['offer' => fn($q) => $q->withTrashed()->select('id', 'title')])
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topOffers
        ]);
    }
    /**
     * 5. سجل مبيعات التاجر بالكامل (Vendor Sales History)
     * (اتنقلت هنا بنفس اللوجيك والـ Queries بتاعتك بالظبط)
     */
    public function vendorSales()
    {
        $vendorId = $this->getVendorId(); // بجيب الـ vendor_id الصح بتاع التاجر اللي عامل login

        $sales = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->with([
                // بنستخدم withTrashed عشان لو العرض اتمسح (Soft Delete) يفضل ظاهر في السجل
                'offer' => fn($q) => $q->withTrashed()->select('id', 'title', 'image'),
                'order:id,order_status,order_date'
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * 6. تفاصيل قطعة مبيوعة (للشحن أو المعاينة)
     * (اتنقلت هنا بنفس اللوجيك والـ Queries بتاعتك بالظبط)
     */
    public function showSoldItem($id)
    {
        $vendorId = $this->getVendorId(); // بجيب الـ vendor_id الصح بتاع التاجر اللي عامل login

        $item = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId);
        })
            ->with([
                'offer' => fn($q) => $q->withTrashed(),
                'order.customer:id,name,email' // بيانات العميل اللي اشترى
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }
}