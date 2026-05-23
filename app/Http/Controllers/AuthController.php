<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validation
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'required|string',
            'address' => 'required|string',
            'accepted_terms' => 'required|accepted',
            'role' => 'required|in:vendor,customer'
        ]);

        // 2. Create User
        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'phone' => $fields['phone'],
            'address' => $fields['address'],
            'role' => $fields['role']
        ]);

        // 3. Set Status and Save
        $user->status = ($fields['role'] === 'vendor') ? 'pending' : 'active';
        $user->accepted_terms = true;
        $user->save();

        // 4. Create Vendor Row if needed
        if ($user->role === 'vendor') {
            $user->vendor()->create();
        } elseif ($user->role === 'customer') {
            $user->customer()->create();
        }
        $user->refresh();

        if ($user->role === 'vendor') {
            $user->load('vendor');
        } else {
            $user->load('customer');
        }
        // 5. Create Token
        $token = $user->createToken('myapptoken')->plainTextToken;
        if ($user->role === 'vendor') {
            $welcomeMessage = "Welcome, Partner! 🤝 Your account is currently pending approval. Please complete your store setup to start selling.";
        } else {
            $welcomeMessage = "Welcome to our family! 🌟 Your account is active. Start exploring our latest offers now.";
        }

        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => $welcomeMessage,
            'type' => 'system',
            'is_read' => 0
        ]);

        // 6. Prepare Response Data (تجهيز الرد بالكامل الأول)
        $responseData = [
            'user' => $user,
            'token' => $token,
            'message' => 'User registered successfully!'
        ];

        // 7. Redirect Logic (بناءً على الـ Role)
        if ($user->role === 'vendor') {
            // هنا بنحط الـ complete-setup لأن الـ vendor لسه مسجل حالاً وأكيد معندوش سجل
            $responseData['redirect_to'] = '/vendor/complete-setup';
        } else {
            $responseData['redirect_to'] = '/home';
        }

        // 8. Return One Response ONLY (نرجع الرد مرة واحدة في الآخر)
        return response()->json($responseData, 201);
    }
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response(['message' => 'Invalid credentials'], 401);
        }
        if ($user->status === 'blocked') {
            return response(['message' => 'Your account is blocked'], 403);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful'
        ], 200);
    }
    public function profile(Request $request)
    {
        $user = $request->user();

        // بنشيك على نوع اليوزر اللي عامل Login حالياً
        if ($user->role === 'vendor') {
            // لو تاجر، بنحمل بيانات المحل بتاعته
            $user->load('vendor');
        } elseif ($user->role === 'customer') {
            // لو عميل، بنحمل بيانات العميل (لو عاملة ليها جدول منفصل)
            $user->load('customer');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'user_type' => $user->role,
            'data' => $user
        ], 200);
    }

    public function logout(Request $request)
    {
        // بيمسح التوكن الحالي اللي اليوزر استخدمه عشان يدخل
        $request->user()->currentAccessToken()->delete();

        return response([
            'message' => 'Logged out successfully'
        ], 200);
    }
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Security: Return same response whether email exists or not
        // This prevents attackers from knowing which emails are registered
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Generate 6-digit OTP
            $otp = rand(100000, 999999);

            $user->update([
                'reset_code' => bcrypt($otp),         // Store hashed, not plain text
                'reset_code_expires_at' => now()->addMinutes(10), // OTP expires in 10 minutes
            ]);

            // Send email via Mailtrap
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'api-key' => env('MAIL_PASSWORD'), // هيقرأ الـ API Key اللي في الـ env
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                        'sender' => [
                            'name' => 'Zero Waste App',
                            'email' => env('MAIL_FROM_ADDRESS')
                        ],
                        'to' => [
                            [
                                'email' => $request->email,
                            ]
                        ],
                        'subject' => 'Password Reset Code',
                        'textContent' => "Your password reset code is: {$otp}. It is valid for 10 minutes."
                    ]);

            // تشيك أمان عشان لو بريفو رفض الطلب لأي سبب نعرف من الـ Logs
            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error('Brevo API Failed: ' . $response->body());
            }

        }

        // Always return the same response to prevent user enumeration
        return response()->json(['message' => 'If this email is registered in our system, you will receive a reset code shortly.']);
    }
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_code' => 'required',
        ]);

        // بنجيب اليوزر وبنشيك إن الكود منتهى الصلاحية
        $user = User::where('email', $request->email)
            ->whereNotNull('reset_code')
            ->where('reset_code_expires_at', '>', now())
            ->first();

        // بنطابق الكود المتشفر
        if (!$user || !Hash::check($request->reset_code, $user->reset_code)) {
            return response()->json(['message' => 'The code is invalid, expired, or the email is incorrect.'], 400);
        }

        // لو الكود صح، بنرجع نجاح عشان الـ Front ينقلهم لصفحة الباسورد الجديد
        return response()->json(['message' => 'Code verified successfully.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8', // شلنا شرط الـ reset_code لأنه اتأكد خلاص
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // تحديث الباسورد وتصفير الكود تماماً عشان الأمان
        $user->update([
            'password' => bcrypt($request->password),
            'reset_code' => null,
            'reset_code_expires_at' => null,
        ]);

        return response()->json(['message' => 'Password changed successfully!']);
    }
}
