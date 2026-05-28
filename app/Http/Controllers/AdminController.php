<?php

namespace App\Http\Controllers;

use App\Models\admin;
use App\Models\offer;
use App\Models\order;
use App\Models\review;
use App\Models\User;
use App\Models\vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use illuminate\Support\Facades\Hash;
class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()  // hygbly kol el customers
    {
        // بنجيب المستخدمين اللي نوعهم كاستمر ومعاهم علاقة الـ customer من الداتابيز
        $customers = User::where('role', 'customer')
            ->with('customer')
            ->get()
            ->map(function ($user) {
                return [
                    // 🟢 الـ id بتاع الكاستمر الفعلي من جدول customers
                    'id' => $user->customer ? $user->customer->id : null,

                    // 🟢 الـ user_id الصريح اللي هما عايزينه
                    'user_id' => $user->id,

                    'name' => $user->name,
                    'email' => $user->email,

                    // 🟢 بنقراهم من الـ $user علطول لأنهم عندك في جدول الـ users
                    'phone' => $user->phone,
                    'address' => $user->address,

                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'admin_id' => $user->customer ? $user->customer->admin_id : null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'count' => $customers->count(),
            'data' => $customers
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function pendingVendors()
    {
        // 1. هنجيب المحلات اللي حالتها pending بس مع بيانات اليوزر
        $pendingVendors = vendor::with([
            'user' => function ($query) {
                // 🟢 سيبنا اليوزر يجيب حقوله كاملة عشان نضمن إن الـ Foreign Key يربط صح وميجيبش 500
                $query->select('*');
            }
        ])
            // 2. الحقول اللي إنتي عايزاها بالظبط من جدول الـ Vendor
            // 🔥 لو ضربت 500، جربي تشوفي هل في المايجريشن مكتوبة business_name ولا name بس؟ أو vendor_type كابيتال؟
            ->select('id', 'user_id', 'business_name', 'logo', 'vendor_type', 'created_at')
            ->where('status', 'pending')
            ->latest()
            ->get();

        // 3. نبعت الـ Response النضيف بتاعك
        if ($pendingVendors->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No vendors waiting for approval.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => 'success',
            'count' => $pendingVendors->count(),
            'data' => $pendingVendors
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function showPendingDocs($id)
    {
        // بنجيب التاجر بشرط يكون pending فقط
        $vendor = vendor::with('user')
            ->where('status', 'pending')
            ->where('id', $id)
            ->first();

        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'This vendor is not pending or does not exist.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $vendor // هنا هيشوف السجل والبطاقة وكل حاجة
        ]);
    }
    public function accept($id)
    {
        $vendor = vendor::findOrFail($id);

        $vendor->update([
            'status' => 'active',
            'admin_id' => auth()->id(), // بنسجل مين الأدمن اللي وافق عليه
        ]);
        $vendor->user->update(['status' => 'active']); // لازم اليوزر نفسه يتفعل عشان يعرف يعمل Login

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor approved successfully. They are now active.',
            'data' => $vendor
        ], 200);
    }

    /**
     * رفض التاجر مع ذكر السبب
     */
    public function reject(Request $request, $id)
    {
        // بنعمل Validation لسبب الرفض عشان ميكونش فاضي
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $vendor = vendor::findOrFail($id);

        $vendor->user->update([
            'status' => 'rejected',
        ]);
        // لو ضفتي حقل rejection_reason في الميجريشن زي ما اتفقنا
        $vendor->update([
            'rejection_reason' => $request->reason,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor request rejected. The reason has been recorded.',
            'data' => $vendor->load('user')
        ], 200);
    }
    public function blockUser($id)
    {
        // بنجيب اليوزر بالـ id
        $user = User::findOrFail($id);

        // تحديث الحالة لـ blocked
        $user->update([
            'status' => 'blocked'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User has been blocked successfully. They can no longer login.',
            'data' => $user
        ], 200);
    }

    public function unblockUser($id)
    {
        $user = User::findOrFail($id);

        // بنرجعه active (أو pending حسب نظامك)
        $user->update(['status' => 'active']);

        return response()->json([
            'message' => 'User unblocked successfully.'
        ], 200);
    }
    public function destroyVendor($id)
    {
        // 1. بندور على الفيندور الأول بالـ ID بتاعه
        $vendor = vendor::findOrFail($id);

        // 2. بنجيب اليوزر المرتبط بالفيندور ده
        $user = $vendor->user;

        // 3. بنمسح اليوزر
        // وبسبب الـ cascade، سطر الفيندور في جدول الـ vendors هيتمسح لوحده فوراً
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor and their user account deleted successfully.'
        ], 200);
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // بنبحث عن اليوزر بالـ id وبنتأكد إنه عميل
        $customer = User::where('role', 'customer')
            ->with('customer')
            ->find($id);

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found or user is not a customer'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $customer
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            // لو حابة تسمحي بتغيير الباسورد من هنا
        ]);
        // 3. التحديث باستخدام المتغير $data (اللي حصل له Validate) 
        // مش باستخدام $request->all()
        $user->update($data);
        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => "Admin profile information has been successfully modified.",
            'type' => 'system',
            'is_read' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        // 1. الـ Validation الخاص بالـ Admin
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // 2. التحقق من الباسورد القديمة
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The current password you entered is incorrect.'
            ], 422);
        }

        // 3. تحديث الباسورد المشفرة
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // 4. إرسال إشعار أمني شديد اللهجة للـ Admin
        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => "Security Alert: Admin password was updated. If this wasn't you, check logs immediately.",
            'type' => 'system',
            'is_read' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Admin password changed successfully!'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        $user = User::findOrFail($id);

        // تأكدي إن الأدمن بيمسح كاستمر مش بيمسح أدمن زيه (حماية إضافية)
        if ($user->role !== 'customer') {
            return response()->json(['message' => 'You can only delete customers'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }
    public function deleteOfferByAdmin($id)
    {
        // 1. البحث عن العرض
        $offer = offer::find($id);

        // 2. لو العرض مش موجود
        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Offer not found.'
            ], 404);
        }

        // 3. مسح العرض
        $offer->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Offer deleted successfully by Admin.'
        ], 200);
    }
    public function deleteAccount()
    {
        // عد الأدمنز الموجودين في السيستم
        $adminsCount = User::where('role', 'admin')->count();

        if ($adminsCount <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account. You are the only admin in the system.'
            ], 403);
        }

        $user = Auth::user();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your admin account has been deleted.'
        ]);
    }
    public function listAllOrders()
    {
        // عرض كل الطلبات في السيستم مع بيانات العميل
        $orders = order::with('customer')->latest()->paginate(10);
        return response()->json(['success' => true, 'data' => $orders]);
    }
    public function toggleVisibility($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Access Denied. Admins only.'], 403);
        }
        $review = review::findOrFail($id);

        // بيعكس الحالة (لو true يخليها false والعكس)
        $review->update([
            'is_visible' => !$review->is_visible
        ]);

        return response()->json([
            'message' => 'Review visibility updated!',
            'is_visible' => $review->is_visible
        ]);
    }
}

