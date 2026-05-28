<?php

namespace App\Http\Controllers;

use App\Models\order_item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function vendorSales()
    {
        $sales = order_item::whereHas('offer', function ($query) {
            $query->where('vendor_id', auth()->id());
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
     * 2. تفاصيل قطعة مبيوعة (للشحن أو المعاينة)
     */
    public function showSoldItem($id)
    {
        $item = order_item::whereHas('offer', function ($query) {
            $query->where('vendor_id', auth()->id());
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

    /**
     * 3. تقرير الأرباح (الماليات)
     * بيحسب فقط الأوردرات الـ Completed
     */

    public function salesReport()
    {
        try {
            // 1. بنجيب الـ Vendor Profile بتاع اليوزر الحالي
            $vendorProfile = auth()->user()->vendor;

            if (!$vendorProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'The authenticated user does not have a Vendor profile.'
                ], 403);
            }

            $vendorId = $vendorProfile->id; // الـ ID الصح من جدول الـ vendors

            // 2. اللفة المظبوطة: بنفلتر الـ Items بناءً على الـ Offer المربوط بالـ Branch التابع للفيندور ده
            $report = order_item::whereHas('offer.branch', function ($query) use ($vendorId) {
                // 💡 ملحوظة: لو جدول الـ branches عندك مربوط بـ user_id مش vendor_id، غيري الكلمة اللي تحت لـ 'user_id' ومرري auth()->id()
                $query->where('vendor_id', $vendorId);
            })
                ->whereHas('order', function ($query) {
                    $query->where('order_status', 'completed');
                })
                ->select(
                    DB::raw('SUM(price * quantity) as total_revenue'),
                    DB::raw('SUM(quantity) as total_units_sold'),
                    DB::raw('COUNT(DISTINCT order_id) as total_orders')
                )
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => (float) ($report->total_revenue ?? 0),
                    'total_units_sold' => (int) ($report->total_units_sold ?? 0),
                    'total_orders' => (int) ($report->total_orders ?? 0),
                    'currency' => 'EGP'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong inside sales report controller.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. إحصائية الأكثر مبيعاً (Top 5)
     */
    public function topSelling()
    {
        $topOffers = order_item::whereHas('offer', function ($query) {
            $query->where('vendor_id', auth()->id());
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
}
