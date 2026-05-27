<?php

namespace App\Http\Controllers;

use App\Models\order;
use App\Models\User;
use App\Models\offer;
use App\Models\vendor; // متنسيش تعملي import للـ Vendor
use Illuminate\Http\Request;
use Exception;

class DashboardController extends Controller
{
    public function getStats()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => User::where('role', 'customer')->count(),
                    'total_vendors' => User::where('role', 'vendor')->count(),
                    'active_offers' => offer::where('status', 'active')->count(),
                    'expired_offers' => offer::where('status', 'expired')->count(),
                    'total_orders' => order::count(), // 🔥 السطور الجديدة اللي طلبتها
                    'total_revenue' => order::sum('total_amount'), // 🔥 حاسب الإيرادات
                    // 🔥 السطر الجديد السحري اللي هيشغل الـ Chart البياني للأسبوع
                    'weekly_data' => order::selectRaw('DAYNAME(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue')
                        ->where('created_at', '>=', now()->subDays(7))
                        ->groupBy('day')
                        ->get(),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stats',
                'error' => $e->getMessage()
            ], 500);
        }
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