<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\offer;
use App\Models\vendor; // متنسيش تعملي import للـ Vendor
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getStats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_customers'  => User::where('role', 'customer')->count(),
                'total_vendors'    => User::where('role', 'vendor')->count(),
                'active_offers'    => offer::where('status', 'active')->count(),
                'expired_offers'   => offer::where('status', 'expired')->count(),
            ]
        ]);
    }

    public function getRecentActivity()
    {
        return response()->json([
            'success' => true,
            'data' => [
                // آخر 5 يوزرز سجلوا
                'latest_users'  => User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']),
                
                // آخر 5 عروض مع اسم المحل (business_name)
                'latest_offers' => offer::with(['vendor' => function($query) {
                    $query->select('id', 'business_name', 'logo'); // بنجيب بيانات المحل مش اليوزر
                }])->latest()->take(5)->get(),
            ]
        ]);
    }
}