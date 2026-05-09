<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// class CheckStatus
// {
//     /**
//      * Handle an incoming request.
//      *
//      * @param  Closure(Request): (Response)  $next
//      */
//     public function handle(Request $request, Closure $next)
//     {
//         $user = auth()->user();

//         // 1. التأكد إن اليوزر هو Vendor فعلاً
//         if ($user->type !== 'vendor') {
//             return response()->json([
//                 'message' => 'Access Denied! This area is for vendors only.',
//                 'status' => 'not_a_vendor'
//             ], 403);
//         }

//         // 2. تشبيك الـ Status (سواء pending أو blocked)
//         if ($user->status !== 'active') {
//             $message = $user->status === 'pending'
//                 ? 'Your account is under review. Please wait for admin approval.'
//                 : 'Your account has been blocked. Please contact support.';

//             return response()->json([
//                 'message' => $message,
//                 'status' => $user->status . '_vendor'
//             ], 403);
//         }

//         // لو Vendor و Active، بنعديه بسلام
//         return $next($request);
//     }
// }