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
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'required|string',
            'address' => 'required|string',
            'accepted_terms' => 'required|accepted',
            'role' => 'required|in:vendor,customer'
        ]);
        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'phone' => $fields['phone'],
            'address' => $fields['address'],
            'role' => $fields['role']
        ]);
        $user->status = ($fields['role'] === 'vendor') ? 'pending' : 'active';
        $user->accepted_terms = true;
        $user->save();

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
        $responseData = [
            'user' => $user,
            'token' => $token,
            'message' => 'User registered successfully!'
        ];
        if ($user->role === 'vendor') {
            
            $responseData['redirect_to'] = '/vendor/complete-setup';
        } else {
            $responseData['redirect_to'] = '/home';
        }
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
        $finalRole = $user->role;

        if ($user->role === 'admin') {
            $finalRole = $user->admin ? $user->admin->permission_level : 'support';
        }

        $token = $user->createToken('myapptoken')->plainTextToken;
        $userData = $user->toArray();
        $userData['role'] = $finalRole; 

        return response([
            'user' => $userData,
            'token' => $token,
            'message' => 'Login successful'
        ], 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response([
            'message' => 'Logged out successfully'
        ], 200);
    }
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $otp = rand(100000, 999999);

            $user->update([
                'reset_code' => bcrypt($otp),         
                'reset_code_expires_at' => now()->addMinutes(10), 
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'api-key' => env('MAIL_PASSWORD'), 
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

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error('Brevo API Failed: ' . $response->body());
            }

        }

        return response()->json(['message' => 'If this email is registered in our system, you will receive a reset code shortly.']);
    }
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_code' => 'required',
        ]);

        $user = User::where('email', $request->email)
            ->whereNotNull('reset_code')
            ->where('reset_code_expires_at', '>', now())
            ->first();

        if (!$user || !Hash::check($request->reset_code, $user->reset_code)) {
            return response()->json(['message' => 'The code is invalid, expired, or the email is incorrect.'], 400);
        }

        return response()->json(['message' => 'Code verified successfully.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8', 
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->update([
            'password' => bcrypt($request->password),
            'reset_code' => null,
            'reset_code_expires_at' => null,
        ]);

        return response()->json(['message' => 'Password changed successfully!']);
    }
    public function profile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'vendor') {
            $user->load('vendor');
        } elseif ($user->role === 'customer') {
            $user->load('customer');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'user_type' => $user->role,
            'data' => $user
        ], 200);
    }
}
