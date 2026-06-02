<?php

namespace App\Http\Controllers;

use App\Models\order;
use Illuminate\Http\Request;
use App\Models\order_item;
use App\Models\offer;
use App\Models\branch;
use Illuminate\Support\Facades\DB;

class VendorDashboardController extends Controller
{
    private function getVendorId()
    {
        return auth()->user()->vendor->id;
    }

    // دالة لجلب فروع التاجر (عشان الفرونت إند يقرر يظهر الـ Sidebar ولا لا)
    public function getVendorBranches()
    {
        $vendorId = $this->getVendorId();
        $branches = branch::where('vendor_id', $vendorId)->select('id', 'branch_name')->get();

        return response()->json([
            'success' => true,
            'count' => $branches->count(),
            'data' => $branches
        ]);
    }

    // 1. الكروت الإحصائية والمالية
    public function getOverviewStats(Request $request)
    {
        $vendorId = $this->getVendorId();

        $query = order_item::whereHas('offer.branch', function ($q) use ($vendorId, $request) {
            $q->where('vendor_id', $vendorId);
            if ($request->has('branch_id')) {
                $q->where('branch_id', $request->branch_id);
            }
        });

        $report = $query->whereHas('order', function ($q) {
            $q->where('order_status', 'completed');
        })
            ->select(
                DB::raw('SUM(price * quantity) as total_gross_revenue'),
                DB::raw('SUM(quantity) as total_units_sold'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders')
            )
            ->first();

        $grossRevenue = (float) ($report->total_gross_revenue ?? 0);
        $platformCommission = $grossRevenue * 0.12;
        $netVendorRevenue = $grossRevenue - $platformCommission;

        $activeOffersCount = offer::whereHas('branch', function ($query) use ($vendorId, $request) {
            $query->where('vendor_id', $vendorId);
            if ($request->has('branch_id')) {
                $query->where('id', $request->branch_id);
            }
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
                    'gross_revenue' => $grossRevenue,
                    'platform_deduction' => $platformCommission,
                    'net_revenue' => round($netVendorRevenue, 2),
                    'currency' => 'EGP'
                ]
            ]
        ]);
    }

    // 2. الرسم البياني للمبيعات
    public function getMonthlySalesChart(Request $request)
    {
        $vendorId = $this->getVendorId();

        $monthlyData = order_item::whereHas('offer.branch', function ($q) use ($vendorId, $request) {
            $q->where('vendor_id', $vendorId);
            if ($request->has('branch_id')) {
                $q->where('branch_id', $request->branch_id);
            }
        })
            ->whereHas('order', fn($q) => $q->where('order_status', 'completed'))
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                DB::raw('MONTH(orders.order_date) as month'),
                DB::raw('SUM(order_items.price * order_items.quantity) as monthly_gross')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $chartData = $monthlyData->map(function ($item) {
            $gross = (float) $item->monthly_gross;
            $net = $gross * 0.88;
            return [
                'month' => date("F", mktime(0, 0, 0, $item->month, 10)),
                'net_sales' => round($net, 2)
            ];
        });

        return response()->json(['success' => true, 'data' => $chartData]);
    }

    // 3. الأكثر مبيعاً
    public function topSelling(Request $request)
    {
        $vendorId = $this->getVendorId();

        $topOffers = order_item::whereHas('offer.branch', function ($q) use ($vendorId, $request) {
            $q->where('vendor_id', $vendorId);
            if ($request->has('branch_id')) {
                $q->where('branch_id', $request->branch_id);
            }
        })
            ->select('offer_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('offer_id')
            ->orderByDesc('total_sold')
            ->with(['offer' => fn($q) => $q->withTrashed()->select('id', 'title')])
            ->take(5)
            ->get();

        return response()->json(['success' => true, 'data' => $topOffers]);
    }

    // 4. سجل المبيعات
    public function vendorSales(Request $request)
    {
        $vendorId = $this->getVendorId();

        $sales = order_item::whereHas('offer.branch', function ($q) use ($vendorId, $request) {
            $q->where('vendor_id', $vendorId);
            if ($request->has('branch_id')) {
                $q->where('branch_id', $request->branch_id);
            }
        })
            ->with([
                'offer' => fn($q) => $q->withTrashed()->select('id', 'title', 'image'),
                'order:id,order_status,order_date'
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $sales]);
    }

    // 5. تفاصيل قطعة مبيوعة
    public function showSoldItem($id)
    {
        try {
            $vendorId = $this->getVendorId();
            $item = order_item::whereHas('offer.branch', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })
                ->with([
                    'offer' => fn($q) => $q->withTrashed(),
                    'order.customer.user:id,name,email,phone'
                ])
                ->findOrFail($id);

            return response()->json(['success' => true, 'data' => $item]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong!'], 500);
        }
    }
    public function ordersChart(Request $request)
    {
        $vendor = \App\Models\vendor::where('user_id', auth()->id())->first();
        $branchId = $request->query('branch_id');

        $query = order::where('vendor_id', $vendor->id)
            ->whereYear('created_at', now()->year);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->selectRaw('DATE(created_at) as date, COUNT(*) as total_orders')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $orders]);

    }
}