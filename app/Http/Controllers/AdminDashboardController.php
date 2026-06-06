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
    public function getOverviewStats()
    {

        $totalSales = order_item::whereHas('order', function ($query) {
            $query->where('order_status', 'completed');
        })
            ->select(DB::raw('SUM(price * quantity) as total_base_sales'))
            ->first();
        $baseSales = (float) ($totalSales->total_base_sales ?? 0);
        $customerCommissions = round($baseSales * 0.06, 2);
        $vendorCommissions = round($baseSales * 0.12, 2);
        $platformPureProfit = $customerCommissions + $vendorCommissions;
        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => User::where('role', 'customer')->count(),
                'total_vendors' => User::where('role', 'vendor')->count(),
                'total_orders' => order::count(),
                'total_active_offers' => offer::where('status', 'active')->count(),
                'financials' => [
                    'total_market_sales' => $baseSales,
                    'customer_fees_6_pct' => $customerCommissions,
                    'vendor_fees_12_pct' => $vendorCommissions,
                    'net_platform_profit' => $platformPureProfit,
                    'currency' => 'EGP'
                ]
            ]
        ]);
    }
    public function getMonthlyEarningsChart()
    {

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

        $chartData = $monthlyData->map(function ($item) {
            $sales = (float) $item->monthly_base_sales;
            $platformProfit = $sales * 0.18;

            return [
                'month' => date("F", mktime(0, 0, 0, $item->month, 10)),
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

                    'latest_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']),


                    'latest_offers' => offer::latest()->take(5)->get(['id', 'title', 'status', 'created_at']),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}