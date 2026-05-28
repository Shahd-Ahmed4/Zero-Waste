<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\customer;
use App\Models\vendor;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    // 1. دالة إضافة أو حذف الفيندور من المفضلة (Toggle)
    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
        ]);

        // نجيب الـ customer المرتبط بالمستخدم الحالي
        $customer = customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return response()->json(['message' => 'This user does not have a customer account.'], 404);
        }

        // عمل الـ Toggle بذكاء
        $status = $customer->favoriteVendors()->toggle($request->vendor_id);

        if (count($status['attached']) > 0) {
            return response()->json(['message' => 'Vendor added to favorites successfully.'], 200);
        }

        return response()->json(['message' => 'Vendor removed from favorites successfully.'], 200);
    }

    // 2. دالة جلب المفضلة بالترتيب الذكي (حسب الموقع وصلاحية العروض)
    public function getFavorites(Request $request)
    {
        try {
            // الفرونت إند بيبعت لوكيشن الزبون الحالي عشان نحسب المسافة
            $request->validate([
                'customer_lat' => 'required|numeric',
                'customer_long' => 'required|numeric',
            ]);

            $customer = customer::where('user_id', auth()->id())->first();

            if (!$customer) {
                return response()->json(['message' => 'Customer account not found.'], 404);
            }

            $lat = $request->customer_lat;
            $lng = $request->customer_long;

            $favorites = $customer->favoriteVendors()
                ->with([
                    'branches' => function ($query) use ($lat, $lng) {
                        $query->select('*')
                            ->selectRaw(
                                "( 6371 * acos( cos( radians(?) ) * cos( radians(lat) ) * cos( radians(long) - radians(?) ) + sin( radians(?) ) * sin( radians(lat) ) ) ) AS distance",
                                [$lat, $lng, $lat]
                            )
                            ->orderBy('distance', 'asc')
                            ->with([
                                'offers' => function ($offerQuery) {
                                    $offerQuery->where('quantity_available', '>', 0)
                                        ->where('expiration_time', '>', now())
                                        ->orderBy('expiration_time', 'asc');
                                }
                            ]);
                    }
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $favorites
            ], 200);

        } catch (\Exception $e) {
            // 🟢 هنا اللعبة! السيرفر هيرجعلك اسم الغلط والسطر كاملاً في الـ Response بدل كلمة Server Error المبهمة
            return response()->json([
                'message' => 'Server Error Caught!',
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}