<?php

namespace App\Http\Controllers;

use App\Models\order;
use App\Models\review;
use App\Models\vendor;
use Illuminate\Http\Request;
use App\Models\offer;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();

        if (!$user || $user->role !== 'admin') {
            $query = vendor::whereHas('user', function ($q) {
                $q->where('status', 'active');
            })->select([
                        'id',
                        'business_name',
                        'logo',
                        'vendor_type'
                    ]);

            // الفلاتر العادية للزوار والمستخدمين
            if ($request->has('search')) {
                $query->where('business_name', 'LIKE', '%' . $request->search . '%');
            }
            if ($request->has('type')) {
                $query->where('vendor_type', $request->type);
            }

            $vendors = $query->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $vendors
            ], 200);
        }
        // 🟢 لو هو Admin ياشا.. بننفذ اقتراح الفرونت إند هنا علطول ونقفل الـ Response
        else {
            // بنجيب كل الفيندوز بالـ الكولومز كاملة مع اليوزر
            $query = vendor::with('user');

            // لو الأدمن باعت سيرش أو فلتر برضه يشتغل معاه ميبقاش واقف
            if ($request->has('search')) {
                $query->where('business_name', 'LIKE', '%' . $request->search . '%');
            }
            if ($request->has('type')) {
                $query->where('vendor_type', $request->type);
            }

            $vendors = $query->paginate(10);

            // رفع الـ status بره عشان خاطر الفرونت إند
            $vendors->getCollection()->transform(function ($vendor) {
                $vendor->status = $vendor->user->status ?? 'pending';
                return $vendor;
            });

            return response()->json([
                'status' => 'success',
                'data' => $vendors
            ], 200);
        }
    }

    public function completesetup(Request $request)
    {
        $user = auth()->user(); // السطر ده هيخليكِ تشوفي الـ status والـ role
        $vendor = $user->vendor; // وده يخليكِ تشوفي بيانات التاجر

        if ($user->status === 'active') {
            return response()->json([
                'status' => 'success',
                'message' => 'Your account is already active and approved!',
                'redirect_to' => '/home'
            ], 200);
        }

        if ($vendor->commercial_register !== null && $user->status === 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Your documents are already under review. You cannot resubmit at this time.'
            ], 403);

        }
        // 1. الـ Validation: نتأكد إن كل حاجة مبعوتة وصح
        $request->validate([
            'business_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            //'opening_hours' => 'required|string',
            'vendor_type' => 'required|string',
            // 'store_address' => 'required|string',
            // 'contact_email' => 'required|email|max:255',
            // 'contact_phone' => 'required|string',
            'tax_number' => 'required|string|unique:vendors,tax_number,' . $vendor->id,
            'commercial_register' => 'required|file|mimes:pdf,jpg,png|max:4096',
            'tax_card' => 'required|file|mimes:pdf,jpg,png|max:4096',
            //  'lat' => 'nullable|numeric',
            // 'long' => 'nullable|numeric',
        ]);


        // 2. رفع اللوجو (لو موجود)
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            $logoName = time() . '_logo_' . $logoFile->getClientOriginalName();
            $logoFile->move(public_path('uploads/vendors/logos'), $logoName);
            $vendor->logo = 'uploads/vendors/logos/' . $logoName;
        }

        // 3. رفع السجل التجاري
        if ($request->hasFile('commercial_register')) {
            $crFile = $request->file('commercial_register');
            $crName = time() . '_cr_' . $crFile->getClientOriginalName();
            $crFile->move(public_path('uploads/vendors/docs'), $crName);
            $vendor->commercial_register = 'uploads/vendors/docs/' . $crName;
        }

        // 4. رفع البطاقة الضريبية
        if ($request->hasFile('tax_card')) {
            $tcFile = $request->file('tax_card');
            $tcName = time() . '_tc_' . $tcFile->getClientOriginalName();
            $tcFile->move(public_path('uploads/vendors/docs'), $tcName);
            $vendor->tax_card = 'uploads/vendors/docs/' . $tcName;
        }

        // 5. حفظ باقي البيانات النصية
        $vendor->update([
            'business_name' => $request->business_name,
            //'store_address' => $request->store_address,
            // 'opening_hours' => $request->opening_hours,
            'vendor_type' => $request->vendor_type,
            // 'contact_email' => $request->contact_email,
            // 'contact_phone' => $request->contact_phone,
            'tax_number' => $request->tax_number,
            // 'lat' => $request->lat,
            // 'long' => $request->long,
        ]);
        if ($user->status === 'rejected') {
            $user->update(['status' => 'pending']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully, waiting for admin approval!'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = auth('sanctum')->user();

        // 1. نبدأ بناء الاستعلام مع تحميل الفروع "والعروض اللي جوه الفروع"
        $query = vendor::with([
            'branches' => function ($q) {
                $q->where('status', 'active')
                    ->with([
                        'offers' => function ($oq) {
                            $oq->where('status', 'active'); // بنجيب العروض النشطة لكل فرع
                        }
                    ]);
            }
        ]);

        // 2. لو المستخدم مش أدمن (كاستمر أو زائر)
        if (!$user || $user->role !== 'admin') {
            $query->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
                ->select(['id', 'business_name', 'logo', 'vendor_type']);
            // بنختار حقول محددة عشان الخصوصية
        }
        // 3. لو أدمن
        else {
            // الأدمن يشوف كل حاجة بما فيها بيانات اليوزر (الإيميل، الحالة، إلخ)
            $query->with(['user']);
        }

        $vendor = $query->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $vendor
        ]);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'business_name' => 'sometimes|string|max:255',
            'logo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'commercial_register' => 'sometimes|mimes:pdf,jpg,png,jpeg|max:5120',
            'tax_card' => 'sometimes|mimes:pdf,jpg,png,jpeg|max:5120',
            'tax_number' => 'sometimes|nullable|string|max:100'
        ]);
        $userData = $request->only(['name', 'email', 'phone']);
        if (!empty($userData)) {
            $user->update($userData);
        }
        // بجيب البيانات النصية اللي اتبعتت بس
        $data = $request->only(['business_name', 'tax_number']);
        // برفع اللوجو لو موجود
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            $logoName = time() . '_logo_' . $logoFile->getClientOriginalName();
            $logoFile->move(public_path('uploads/vendors/logos'), $logoName);
            $data['logo'] = 'uploads/vendors/logos/' . $logoName;
        }

        // 🟢 2. تعديل رفع السجل التجاري لـ public المباشر
        if ($request->hasFile('commercial_register')) {
            $crFile = $request->file('commercial_register');
            $crName = time() . '_cr_' . $crFile->getClientOriginalName();
            $crFile->move(public_path('uploads/vendors/docs'), $crName);
            $data['commercial_register'] = 'uploads/vendors/docs/' . $crName;
        }

        // 🟢 3. تعديل رفع البطاقة الضريبية لـ public المباشر
        if ($request->hasFile('tax_card')) {
            $tcFile = $request->file('tax_card');
            $tcName = time() . '_tc_' . $tcFile->getClientOriginalName();
            $tcFile->move(public_path('uploads/vendors/docs'), $tcName);
            $data['tax_card'] = 'uploads/vendors/docs/' . $tcName;
        }

        $vendor->update($data);
        \App\Models\notification::create([
            'user_id' => $request->user()->id, // بنبعت لليوزر صاحب المحل
            'message' => "Business Profile Updated: Your vendor details and store information have been successfully updated.",
            'type' => 'system',
            'is_read' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'user' => $user->load('vendor') // بيرجع البيانات الجديدة للـ Vendor للتأكيد
        ]);
    }
    public function changePassword(Request $request)
    {
        $user = $request->user();

        // 1. الـ Validation الخاص بالباسورد للـ Vendor
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // 2. التحقق إن الباسورد القديمة صحيحة
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The current password you entered is incorrect.'
            ], 422);
        }

        // 3. تحديث الباسورد
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // 4. إرسال إشعار للـ Vendor
        \App\Models\notification::create([
            'user_id' => $user->id,
            'message' => "Security Alert: Your vendor account password has been changed successfully.",
            'type' => 'system',
            'is_read' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor password changed successfully!'
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {

        $user = $request->user();

        // مسح التوكنات الحالية عشان يخرج من السيستم فوراً
        $user->tokens()->delete();

        // مسح اليوزر (وهيمسح معاه الـ vendor record لو عاملة Cascade في الداتابيز)
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Account deleted successfully'
        ], 200);
    }
    public function getOrdersForMe()
    {
        try {
            // 1. التأكد من أن المستخدم الحالي هو فيندور والحصول على بيانات التاجر الخاصة به
            $vendor = auth()->user()->vendor;

            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor profile not found'
                ], 404);
            }

            // 2. جلب الأوردرات المرتبطة بهذا التاجر مباشرة
            // استخدمنا Eager Loading (with) لجلب بيانات العميل والفرع والأصناف في طلب واحد
            $orders = order::where('vendor_id', $vendor->id)
                ->with([
                    'customer:id,user_id',                         // 🟢 جلب الـ customer ومعه الـ user_id الأساسي للربط
                    'customer.user:id,name,phone',
                    'branch:id,branch_name,store_address', // 🟢 تم تصليح name إلى branch_name
                    'items.offer:id,title,discount_price'    // 🟢 تم تصليح price إلى discount_price
                ])
                ->orderByDesc('created_at') // ترتيب من الأحدث للأقدم
                ->get();

            return response()->json([
                'success' => true,
                'count' => $orders->count(),
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            // 🟢 دي عشان لو ضربت تاني تطلع لك رسالة بالسبب بالظبط بدل صفحة الـ HTML
            return response()->json([
                'success' => false,
                'message' => 'Error inside getOrdersForMe',
                'error_debug' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function offerReviews()
    {
        $vendor = Auth::user()->vendor;

        $reviews = review::with(['customer:id,name', 'offer:id,title,branch_id', 'offer.branch:id,branch_name'])
            ->whereHas('offer.branch', function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);
            })
            ->latest()
            ->get();

        return response()->json(['vendor_name' => $vendor->business_name, 'total_reviews' => $reviews->count(), 'reviews' => $reviews]);
    }

    public function showOfferReviews($offer_id)
    {
        // 1. الحصول على الفيندور الحالي
        $vendor = auth()->user()->vendor;

        // 2. التأكد إن الأوفر ملك للفيندور ده عن طريق "الفرع"
        // بنقول للارافل: هات الأوفر اللي الـ ID بتاعه كذا، وبشرط إن الفرع بتاعه يخص الفيندور ده
        $offer = offer::where('id', $offer_id)
            ->whereHas('branch', function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);
            })->first();

        if (!$offer) {
            return response()->json(['message' => 'Offer not found or access denied.'], 404);
        }

        // 3. جلب المراجعات مع بيانات العميل
        $reviews = $offer->reviews()->with('customer:id,name')->get();

        return response()->json([
            'status' => 'success',
            'offer_title' => $offer->title, // حتة ذوقية عشان الفرونت إند
            'data' => $reviews
        ]);
    }
}