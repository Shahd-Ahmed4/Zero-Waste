<?php

namespace App\Http\Controllers;

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
                    'latest_offers' => offer::with([
                        'vendor' => function ($query) {
                            // 🔥 تكة مهمة: لازم تزودي الـ user_id (أو الفوراين كي اللي عندك في جدول الـ vendors) عشان العلاقة تتربط صح وماتضربش null
                            $query->select('id', 'user_id', 'business_name', 'logo');
                        }
                    ])->latest()->take(5)->get(),
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