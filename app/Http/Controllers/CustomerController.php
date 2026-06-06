<?php

namespace App\Http\Controllers;

use App\Models\customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
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
        ]);

        // 4. تحديث جدول الـ users
        $user->update($data);

        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => "Your profile information has been updated successfully.",
            'type' => 'system',
            'is_read' => 0
        ]);

        // 5. لو عندك جدول منفصل اسمه customers فيه بيانات تانية (زي نقاط الولاء مثلاً)
        // $user->customer()->update($request->only(['some_other_field']));

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully!',
            'user' => $user->load('customer') // بنرجع البيانات الجديدة مع تفاصيل العميل
        ], 200);
    }
    public function changePassword(Request $request)
    {
        // 1. اليوزر اللي عامل Login حالياً
        $user = $request->user();

        // 2. الـ Validation (التأكد من الباسورد القديمة والجديدة)
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // 3. التحقق إن الباسورد القديمة اللي كتبها صحيحة ومطابقة للي في الداتابيز
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The current password you entered is incorrect.'
            ], 422); // كود 422 معناه ValidationError
        }

        // 4. تشفير وتحديث الباسورد الجديدة
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // 5. إرسال إشعار أمني للمستخدم
        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => "Security Alert: Your password has been changed successfully.",
            'type' => 'system',
            'is_read' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully!'
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
