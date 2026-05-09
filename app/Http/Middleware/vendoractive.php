<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class vendoractive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // 1. التأكد إن اليوزر هو Vendor فعلاً
        if ($user->role !== 'vendor') {
            return response()->json([
                'message' => 'Access Denied! This area is for vendors only.',
                'status' => 'not_a_vendor'
            ], 403);
        }

        // 2. تشبيك الـ Status (سواء pending أو blocked)
        if ($user->status !== 'active') {
            // لو مرفوض، قولي له ليه
            if ($user->status === 'rejected') {
                $message = 'Your documents were rejected: ' . $user->rejection_reason;
            } elseif ($user->status === 'pending') {
                $message = 'Your account is under review. Please wait for admin approval.';
            } else {
                $message = 'Your account has been blocked.';
            }
            return response()->json([
                'message' => $message,
                'status' => $user->status . '_vendor'
            ], 403);
        }

        // لو Vendor و Active، بنعديه بسلام
        return $next($request);
    }
}
