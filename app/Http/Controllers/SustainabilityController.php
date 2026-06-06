<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\order_item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SustainabilityController extends Controller
{
    private $co2Factor = 1.25;
    public function getAdminMetrics()
    {
        try {
            $mealsSaved = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->sum('quantity');
            $recoveredRevenue = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->selectRaw('SUM(price * quantity) as total')->value('total') ?? 0;
            $consumerSavings = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })->selectRaw('SUM((original_price - price) * quantity) as savings')->value('savings') ?? 0;
            $co2Prevented = $mealsSaved * $this->co2Factor;
            $chartData = order_item::whereHas('order', function ($q) {
                $q->where('order_status', 'completed');
            })
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->selectRaw('MONTHNAME(orders.order_date) as month, MONTH(orders.order_date) as month_num, SUM(order_items.quantity) as meals')
                ->groupBy('month', 'month_num')
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
    public function getVendorMetrics(Request $request)
    {
        try {
            $vendorId = auth()->user()->vendor->id;
            $query = order_item::whereHas('order', function ($q) use ($vendorId, $request) {
                $q->where('order_status', 'completed')
                    ->where('vendor_id', $vendorId);
                if ($request->has('branch_id')) {
                    $q->where('branch_id', $request->branch_id);
                }
            });
            $mealsSaved = $query->sum('quantity');
            $recoveredRevenue = $query->selectRaw('SUM(price * quantity) as total')
                ->value('total') ?? 0;
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
    public function getCustomerMetrics()
    {
        try {
            $customer = \App\Models\customer::where('user_id', auth()->id())->first();
            if (!$customer) {
                return response()->json(['message' => 'Customer profile not found'], 404);
            }
            $mealsSaved = order_item::whereHas('order', function ($q) use ($customer) {
                $q->where('order_status', 'completed')->where('customer_id', $customer->id);
            })->sum('quantity');
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