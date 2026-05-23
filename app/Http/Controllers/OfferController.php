<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\offer;
use App\Models\branch;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * 1. عرض العروض (العملاء والأدمن)
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();

        // 1. نبدأ بـ Query أساسي ونحدد صراحة أننا نريد معرف العرض أولاً لتجنب تضارب الجداول
        $query = offer::query()->select('offers.*')->with([
            'branch' => function ($q) {
                // نضمن دائماً وضع الـ id والـ vendor_id لتنجح العلاقة
                $q->select('id', 'branch_name', 'store_address', 'lat', 'long', 'vendor_id');
            },
            'branch.vendor:id,business_name,logo'
        ]);

        // 2. تصفية للأدمن vs المستخدم العادي
        if ($user && $user->role === 'admin') {
            // الأدمن يشوف كله
        } else {
            $query->where('offers.status', 'active')
                ->where('expiration_time', '>', now());
        }

        // 3. فلتر نوع الفيندور
        if ($request->filled('vendor_type') && $request->vendor_type !== 'all') {
            $allowedTypes = ['restaurant', 'bakery', 'cafe', 'supermarket', 'hotel', 'others'];

            if (in_array(strtolower($request->vendor_type), $allowedTypes)) {
                $query->whereHas('branch.vendor', function ($q) use ($request) {
                    $q->where('vendor_type', strtolower($request->vendor_type));
                });
            }
        }

        // 4. منطق الترتيب (Sorting)
        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'distance':
                    if ($request->filled('lat') && $request->filled('long')) {
                        $lat = $request->lat;
                        $lon = $request->long;

                        // هنا قمنا بحل تضارب الـ id: نختار id العرض صراحة كـ id ونضع الحقول الأخرى
                        $query->join('branches', 'offers.branch_id', '=', 'branches.id')
                            ->selectRaw("offers.*, offers.id AS id, (6371 * acos(cos(radians(?)) * cos(radians(branches.lat)) * cos(radians(branches.long) - radians(?)) + sin(radians(?)) * sin(radians(branches.lat)))) AS distance", [$lat, $lon, $lat])
                            ->orderBy('distance', 'asc');
                    }
                    break;

                case 'rating':
                    $query->withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', 'desc');
                    break;

                case 'highest_discount':
                    // نضمن الإبقاء على الـ id الأصلي للعرض هنا أيضاً
                    $query->selectRaw('offers.*, offers.id AS id, (original_price - discount_price) as discount_amount')
                        ->orderBy('discount_amount', 'desc');
                    break;

                default:
                    $query->latest('offers.created_at');
                    break;
            }
        } else {
            $query->latest('offers.created_at');
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 2. إضافة عرض جديد (مربوط بفرع)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id', // لازم نحدد الفرع
            'title' => 'required|string',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'quantity_available' => 'required|integer|min:1',
            'original_price' => 'required|numeric',
            'discount_price' => 'required|numeric|lt:original_price',
            'expiration_time' => 'required|date|after:now',
        ]);

        $vendor = Auth::user()->vendor;
        // التأكد إن الفرع يخص التاجر ده
        $branch = $vendor->branches()->find($request->branch_id);

        if (!$branch) {
            return response()->json(['message' => 'Unauthorized: This branch does not belong to you!'], 403);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('offers', 'public');
        }

        // إنشاء العرض تحت الفرع
        $offer = $branch->offers()->create($data);

        // تنبيه العملاء (Notification)
        $customers = \App\Models\customer::all();
        foreach ($customers as $customer) {
            \App\Models\notification::create([
                'user_id' => $customer->user_id,
                'message' => "New Offer from {$vendor->business_name} at branch {$branch->branch_name}: {$offer->title}!",
                'type' => 'alert',
                'is_read' => 0,
            ]);
        }

        return response()->json(['message' => 'Offer created successfully!', 'offer' => $offer], 201);
    }

    /**
     * 3. التاجر يشوف عروضه (من كل الفروع)
     */
    public function myOffers()
    {
        $vendor = Auth::user()->vendor;

        // استخدمنا Eager Loading للـ branch عشان نعرف كل عرض في أنهي فرع
        $offers = $vendor->offers()->with('branch:id,branch_name,store_address')->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'count' => $offers->total(),
            'data' => $offers
        ], 200);
    }

    /**
     * 4. تحديث العرض
     */
    public function update(Request $request, $id)
    {
        $offer = offer::findOrFail($id);
        $vendor = Auth::user()->vendor;

        // التحقق من الملكية (عن طريق الفرع)
        if ($offer->branch->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Unauthorized!'], 403);
        }

        $data = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'title' => 'sometimes|string|max:255',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'sometimes|string',
            'quantity_available' => 'sometimes|integer|min:0',
            'original_price' => 'sometimes|numeric|min:0',
            'discount_price' => 'sometimes|numeric|min:0|lt:original_price',
            'expiration_time' => 'sometimes|date|after:now',
            'status' => 'sometimes|in:active,expired,disabled',
        ]);

        // لو بيغير الفرع، نتأكد إن الفرع الجديد بتاعه برضه
        if (isset($data['branch_id']) && !$vendor->branches()->find($data['branch_id'])) {
            return response()->json(['message' => 'Invalid branch ID'], 403);
        }
        if ($request->hasFile('image')) {
            // بنخزن الصورة في فولدر offers وبنحدث قيمة image في الـ data
            $data['image'] = $request->file('image')->store('offers', 'public');

            $offer->update($data);

            return response()->json(['status' => 'success', 'offer' => $offer]);
        }
    }

    /**
     * 5. تحديث الحالة فقط
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,expired,disabled']);

        $vendor = Auth::user()->vendor;
        // البحث عن العرض في نطاق فروع الفيندور
        $offer = $vendor->offers()->find($id);

        if (!$offer) {
            return response()->json(['message' => 'Offer not found or unauthorized'], 404);
        }

        $offer->update(['status' => $request->status]);

        return response()->json(['status' => 'success', 'message' => 'Status updated']);
    }

    /**
     * 6. مسح العرض
     */
    public function destroy($id)
    {
        $offer = offer::findOrFail($id);
        $vendor = Auth::user()->vendor;

        if ($offer->branch->vendor_id === $vendor->id) {
            $offer->delete();
            return response()->json(['message' => 'Offer deleted successfully']);
        }

        return response()->json(['message' => 'Unauthorized!'], 403);
    }

    /**
     * 7. تقليل الكمية (لعمليات الطلب)
     */
    public function decreaseQuantity($id, $quantityPurchased)
    {
        $offer = offer::findOrFail($id);

        if ($offer->quantity_available >= $quantityPurchased) {
            $offer->decrement('quantity_available', $quantityPurchased);

            if ($offer->fresh()->quantity_available <= 0) {
                $offer->update(['status' => 'disabled']);
            }
        }
    }
    public function show($id)
    {
        // بنجيب العرض مع بيانات الفرع والفيندور اللي فوق الفرع ده
        $offer = offer::with([
            'branch' => function ($q) {
                $q->select('id', 'branch_name', 'store_address', 'lat', 'long', 'vendor_id', 'opening_hours', 'contact_phone');
            },
            'branch.vendor' => function ($q) {
                $q->select('id', 'business_name', 'logo', 'vendor_type');
            }
        ])->findOrFail($id);

        // الحماية: لو العرض مش active
        // بنشيك لو اليوزر مش أدمن "و" مش هو صاحب الفرع اللي نزل العرض
        $isOwner = auth()->user() && auth()->user()->vendor && $offer->branch->vendor_id === auth()->user()->vendor->id;

        if ($offer->status !== 'active' && (!auth()->user() || auth()->user()->role !== 'admin') && !$isOwner) {
            return response()->json(['message' => 'This offer is no longer available'], 410);
        }

        return response()->json([
            'status' => 'success',
            'data' => $offer
        ]);
    }
    public function showVendorOffer($id)
    {
        $user = Auth::user();

        // بنبحث عن العرض جوه "عروض الفيندور" اللي جاية من كل فروعه
        // استخدمنا العلاقة السحرية اللي عملناها في الموديل: offers()
        $offer = $user->vendor->offers()->with('branch')->find($id);

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Offer not found or you do not have permission to view it.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $offer
        ], 200);
    }
}
