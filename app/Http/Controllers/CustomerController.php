<?php

namespace App\Http\Controllers;

use App\Models\customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // 1. اليوزر اللي عامل Login حالياً
        $user = $request->user();

        // 2. Validation (تأكدي من أسماء الأعمدة في الداتابيز عندك)
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',  // لو عايز يغير الباسورد بالمرة
        ]);

        // 3. تشفير الباسورد لو موجودة في الطلب
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // 4. تحديث جدول الـ users
        $user->update($data);
        if (isset($data['password'])) {
            // لو غير الباسورد نبعت إشعار أمني
            \App\Models\notification::create([
                'user_id' => $user->id,
                'message' => "Security Alert: Your password has been changed successfully.",
                'type' => 'system',
                'is_read' => 0
            ]);
        } else {
            // لو حدث بيانات عادية
            \App\Models\notification::create([
                'user_id' => $user->id,
                'message' => "Your profile information has been updated successfully.",
                'type' => 'system',
                'is_read' => 0
            ]);
        }

        // 5. لو عندك جدول منفصل اسمه customers فيه بيانات تانية (زي نقاط الولاء مثلاً)
        // $user->customer()->update($request->only(['some_other_field']));

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully!',
            'user' => $user->load('customer') // بنرجع البيانات الجديدة مع تفاصيل العميل
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        // 1. نجيب اليوزر اللي باعت الـ Request
        $user = $request->user();

        // 2. مسح بياناته من جدول الـ customers الأول (لو فيه علاقة)
        if ($user->customer) {
            $user->customer()->delete();
        }

        // 3. مسح الـ Tokens بتاعته عشان ميعرفش يدخل تاني
        $user->tokens()->delete();

        // 4. مسح اليوزر نفسه من جدول الـ users
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Your account has been deleted successfully. We are sad to see you go!'
        ], 200);
    }
}
