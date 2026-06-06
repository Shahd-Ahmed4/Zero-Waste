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
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = User::where('role', 'customer')
            ->with('customer')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->customer ? $user->customer->id : null,

                    'user_id' => $user->id,

                    'name' => $user->name,
                    'email' => $user->email,

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
    public function StatusVendors(Request $request)
    {
        $status = $request->query('status');

        $query = vendor::with([
            'user' => function ($q) {
                $q->select('id', 'name', 'email', 'phone', 'status');
            }
        ])
            ->select('id', 'user_id', 'business_name', 'logo', 'vendor_type', 'created_at');

        if ($status) {
            $query->whereHas('user', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $vendors = $query->latest()->get();

        if ($vendors->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => $status ? 'No vendors with status: ' . $status : 'No vendors found.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => 'success',
            'count' => $vendors->count(),
            'data' => $vendors
        ]);
    }

    public function showPendingDocs($id)
    {
        $vendor = vendor::whereHas('user', function ($query) {
            $query->where('status', 'pending');
        })
            ->with('user')
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
            'data' => $vendor
        ]);
    }
    public function accept($id)
    {
        try {
            $vendor = vendor::findOrFail($id);

            $adminProfile = auth()->user()->admin;

            if (!$adminProfile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The authenticated user does not have an Admin profile.'
                ], 403);
            }

            $vendor->update([
                'admin_id' => $adminProfile->id,
            ]);

            $vendor->user()->update([
                'status' => 'active'
            ]);

            $vendor->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor approved successfully. They are now active.',
                'data' => $vendor->load('user')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong inside the controller.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
    public function reject($id)
    {
        try {
            $vendor = vendor::findOrFail($id);

            $adminProfile = auth()->user()->admin;
            if (!$adminProfile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The authenticated user does not have an Admin profile.'
                ], 403);
            }

            // تحديث الفيندور
            $vendor->update([
                'admin_id' => $adminProfile->id,
            ]);

            // تحديث اليوزر بالأقواس عشان يسمع في الداتابيز فوراً 🟢
            $vendor->user()->update([
                'status' => 'rejected'
            ]);

            // بنقول للارافيل روح هات الداتا الفرش اللي لسه مكتوبة حالا في الداتابيز
            $vendor->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor has been rejected successfully.',
                'data' => $vendor->load('user')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong inside the controller.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
    public function blockUser($id)
    {
        // بنجيب اليوزر بالـ id
        $user = User::findOrFail($id);

        $user->status = 'blocked';
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User has been blocked successfully. They can no longer login.',
            'data' => $user
        ], 200);
    }

    public function unblockUser($id)
    {
        // بنجيب اليوزر بالـ id
        $user = User::findOrFail($id);

        // 🟢 بنعدل الحالة لـ active مباشرة وبنحفظ بـ save عشان نضمن التسميع في الـ DB
        $user->status = 'active';
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User unblocked successfully.',
            'data' => $user // رجعي الـ data كمان عشان الفرونت إند يشوف الحالة الجديدة لايف
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
        // عرض كل الطلبات في السيستم مع جلب الـ id والـ name فقط من جدول الـ user عن طريق الـ customer
        $orders = order::with([
            'customer.user' => function ($query) {
                $query->select('id', 'name');
            }
        ])->latest()->paginate(10);

        return response()->json(['success' => true, 'data' => $orders]);
    }
    public function toggleVisibility($id)
    {
        // 🟢 مش محتاجين أي تشيك صلاحيات هنا لأن الـ Middleware (checkadmin:super_admin,manager) قايم بالواجب بره!

        $review = review::findOrFail($id);

        // بيعكس الحالة وبيحفظ فوراً في الـ DB
        $review->is_visible = !$review->is_visible;
        $review->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Review visibility updated successfully!',
            'is_visible' => $review->is_visible
        ], 200);
    }
    public function listAllReviews()
    {
        $reviews = review::with([
            'customer.user:id,name',
            'offer:id,title'
        ])
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $reviews]);
    }
}

