<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class checkadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$levels  (تستقبل مسميات الـ Enum من الـ Routes)
     */
    public function handle(Request $request, Closure $next, ...$levels): Response
    {
        $user = Auth::user();

        // 1. التأكد إن المستخدم مسجل دخول وله رتبة 'admin' في جدول الـ users
        if ($user && $user->role === 'admin') {
            
            // 2. الوصول لبيانات الأدمن من جدول الـ admins (عن طريق العلاقة)
            $adminProfile = $user->admin;

            if ($adminProfile) {
                // هنجيب قيمة الـ Enum من كولوم الـ permission_level زي ما طلبتِ
                $currentAdminLevel = $adminProfile->permission_level; 

                // 3. التحقق من الصلاحية:
                // لو الـ levels اللي مبعوتة من الـ Route فاضية، بنعديه.
                // لو مش فاضية، بنشوف هل الـ permission_level بتاع الأدمن موجود فيهم؟
                if (empty($levels) || in_array($currentAdminLevel, $levels)) {
                    return $next($request);
                }

                // لو المستوى غير مسموح له
                return response()->json([
                    'status' => 'error',
                    'message' => "Forbidden: Your level ($currentAdminLevel) is not authorized."
                ], 403);
            }
        }

        // لو مش أدمن
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized: Access restricted to administrators only.'
        ], 401);
    }
}