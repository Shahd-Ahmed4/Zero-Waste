<?php

namespace App\Http\Controllers;
use App\Models\order;
use Illuminate\Support\Facades\Auth;
use App\Models\offer;
use App\Models\branch;
use Illuminate\Http\Request;
use Exception;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class OfferController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            $query = offer::query()->select('offers.id', 'offers.title', 'offers.description', 'offers.image', 'offers.quantity_available', 'offers.original_price', 'offers.discount_price', 'offers.expiration_time', 'offers.status', 'offers.branch_id', 'offers.created_at')
                ->withAvg('reviews', 'rating')
                ->with([
                    'branch' => function ($q) {

                        $q->select('id', 'branch_name', 'store_address', 'lat', 'long', 'vendor_id');
                    },
                    'branch.vendor:id,business_name,logo'
                ]);
            if ($user && $user->role === 'admin') {
            } else {
                $query->where('offers.status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->where('expiration_time', '>', now());
            }
            if ($request->filled('vendor_type') && $request->vendor_type !== 'all') {
                $allowedTypes = ['restaurant', 'bakery', 'cafe', 'supermarket', 'hotel', 'others'];
                if (in_array(strtolower($request->vendor_type), $allowedTypes)) {
                    $query->whereHas('branch.vendor', function ($q) use ($request) {
                        $q->where('vendor_type', strtolower($request->vendor_type));
                    });
                }
            }
            if ($request->filled('sort_by')) {
                switch ($request->sort_by) {
                    case 'distance':
                        if ($request->filled('lat') && $request->filled('long')) {
                            $lat = $request->lat;
                            $lon = $request->long;
                            $query->join('branches', 'offers.branch_id', '=', 'branches.id')
                                ->addSelect(\DB::raw("(6371 * acos(cos(radians($lat)) * cos(radians(branches.lat)) * cos(radians(branches.long) - radians($lon)) + sin(radians($lat)) * sin(radians(branches.lat)))) AS distance"))
                                ->orderBy('distance', 'asc');
                        }
                        break;
                    case 'rating':
                        $query->orderBy('reviews_avg_rating', 'desc');
                        break;

                    case 'highest_discount':
                        $query->addSelect(\DB::raw('(original_price - discount_price) as discount_amount'))
                            ->orderBy('discount_amount', 'desc');
                        break;
                    default:
                        if ($request->filled('lat') && $request->filled('long')) {
                            $lat = $request->lat;
                            $lon = $request->long;
                            $query->join('branches', 'offers.branch_id', '=', 'branches.id')
                                ->addSelect(\DB::raw("(6371 * acos(cos(radians($lat)) * cos(radians(branches.lat)) * cos(radians(branches.long) - radians($lon)) + sin(radians($lat)) * sin(radians(branches.lat)))) AS distance"))
                                ->addSelect(\DB::raw("TIMESTAMPDIFF(MINUTE, NOW(), offers.expiration_time) AS minutes_left"))
                                ->orderBy('distance', 'asc')
                                ->orderBy('minutes_left', 'asc');
                        } else {
                            $query->orderBy('offers.expiration_time', 'asc');
                        }
                        break;
                }
            } else {
                if ($request->filled('lat') && $request->filled('long')) {
                    $lat = $request->lat;
                    $lon = $request->long;
                    $query->join('branches', 'offers.branch_id', '=', 'branches.id')
                        ->addSelect(\DB::raw("(6371 * acos(cos(radians($lat)) * cos(radians(branches.lat)) * cos(radians(branches.long) - radians($lon)) + sin(radians($lat)) * sin(radians(branches.lat)))) AS distance"))
                        ->addSelect(\DB::raw("TIMESTAMPDIFF(MINUTE, NOW(), offers.expiration_time) AS minutes_left"))
                        ->orderBy('distance', 'asc')
                        ->orderBy('minutes_left', 'asc');
                } else {
                    $query->orderBy('offers.expiration_time', 'asc');
                }
            }
            $data = $query->get();
            $data->transform(function ($offer) {
                if (isset($offer->reviews_avg_rating)) {
                    $offer->average_rating = round($offer->reviews_avg_rating ?: 0, 1);
                } else {

                    $offer->average_rating = round($offer->reviews()->avg('rating') ?: 0, 1);
                }
                return $offer;
            });
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while fetching offers!',
                'error_debug' => $e->getMessage()
            ], 500);
        }
    }
public function getSmartRecommendations(Request $request)
{
    $user = auth('sanctum')->user();

    // 1️⃣ لو المستخدم مش عامل Login (ضيف مثلاً)، هنرجعله العروض العادية حسب الوقت لعدم وجود تاريخ شرائي
    if (!$user) {
        $fallbackOffers = offer::where('status', 'active')
            ->where('expiration_time', '>', now())
            ->orderBy('expiration_time', 'asc')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $fallbackOffers
        ]);
    }

    try {
        // 2️⃣ السحر الأول: نكتشف "ذوق مصلحة الزبون" بناءً على تاريخ أوردراته السابقة
        $userPreference = order::where('orders.user_id', $user->id)
            ->join('offers', 'orders.offer_id', '=', 'offers.id')
            ->join('branches', 'offers.branch_id', '=', 'branches.id')
            ->join('vendors', 'branches.vendor_id', '=', 'vendors.id')
            ->select('vendors.vendor_type', \DB::raw('COUNT(*) as order_count'))
            ->groupBy('vendors.vendor_type')
            ->orderBy('order_count', 'desc')
            ->first();

        $favoriteType = $userPreference ? $userPreference->vendor_type : null;

        // 3️⃣ بناء الاستعلام الأساسي — بنعمل JOIN دايماً عشان vendor_type يكون متاح في الـ CASE WHEN
        // ✅ FIX: استبدلنا ->with() بـ manual selects عشان مفيش conflict بين eager loading والـ raw JOINs
        $query = offer::query()
            ->select(
                'offers.id',
                'offers.title',
                'offers.description',
                'offers.image',
                'offers.original_price',
                'offers.discount_price',
                'offers.expiration_time',
                'offers.status',
                'offers.branch_id',
                'offers.created_at',
                // Branch columns
                'branches.branch_name',
                'branches.store_address',
                'branches.lat as branch_lat',
                'branches.long as branch_long',
                'branches.vendor_id',
                // Vendor columns
                'vendors.id as vendor_id_fk',
                'vendors.business_name',
                'vendors.logo',
                'vendors.vendor_type'
            )
            // ✅ FIX: JOIN دايماً هنا مرة وحدة بس — مش جوه الـ if/else عشان نتجنب duplicate JOIN
            ->join('branches', 'offers.branch_id', '=', 'branches.id')
            ->join('vendors', 'branches.vendor_id', '=', 'vendors.id')
            ->where('offers.status', 'active')
            ->where('offers.expiration_time', '>', now());

        // 4️⃣ السحر الثاني: دمج "الموقع" مع "ذوق الزبون الشرائي" في الترتيب (Scoring)
        if ($request->filled('lat') && $request->filled('long')) {
            $lat = (float) $request->lat;
            $lon = (float) $request->long;

            // ✅ FIX: bindings بدل string interpolation عشان نمنع SQL Injection
            $distanceSql = "(6371 * acos(cos(radians(?)) * cos(radians(branches.lat)) * cos(radians(branches.long) - radians(?)) + sin(radians(?)) * sin(radians(branches.lat))))";

            $query->addSelect(\DB::raw("$distanceSql AS distance"))
                  ->addBinding([$lat, $lon, $lat], 'select');

            // ✅ FIX: LEAST() عشان penalty المسافة ميطغاش على bonus الـ favorite type
            // ✅ FIX: binding لـ favoriteType بدل string interpolation
            $scoreSql = "CASE WHEN vendors.vendor_type = ? THEN 15 ELSE 0 END - LEAST($distanceSql * 1.5, 10)";

            $query->addSelect(\DB::raw("($scoreSql) AS recommendation_score"))
                  ->addBinding([$favoriteType ?? 'none', $lat, $lon, $lat], 'select')
                  ->orderBy('recommendation_score', 'desc');

        } else {
            // 5️⃣ لو قافل الـ GPS، هنرتب بناءً على ذوقه الشرائي ثم قرب انتهاء الوقت
            if ($favoriteType) {
                // ✅ FIX: binding بدل string interpolation في orderByRaw
                $query->orderByRaw("CASE WHEN vendors.vendor_type = ? THEN 0 ELSE 1 END", [$favoriteType])
                      ->orderBy('offers.expiration_time', 'asc');
            } else {
                $query->orderBy('offers.expiration_time', 'asc');
            }
        }

        $recommendedOffers = $query->take(10)->get();

        // ✅ إعادة تشكيل الـ response عشان يشبه شكل ->with() القديم وما يأثرش على الـ frontend
        $recommendedOffers->transform(function ($offer) {
            $offer->branch = (object) [
                'id'            => $offer->branch_id,
                'branch_name'   => $offer->branch_name,
                'store_address' => $offer->store_address,
                'lat'           => $offer->branch_lat,
                'long'          => $offer->branch_long,
                'vendor_id'     => $offer->vendor_id,
                'vendor'        => (object) [
                    'id'            => $offer->vendor_id_fk,
                    'business_name' => $offer->business_name,
                    'logo'          => $offer->logo,
                    'vendor_type'   => $offer->vendor_type,
                ],
            ];

            // نحذف الـ flat columns الزيادة من الـ offer مباشرةً
            unset(
                $offer->branch_name, $offer->store_address,
                $offer->branch_lat, $offer->branch_long,
                $offer->vendor_id, $offer->vendor_id_fk,
                $offer->business_name, $offer->logo, $offer->vendor_type
            );

            return $offer;
        });

        return response()->json([
            'status'                     => 'success',
            'message'                    => 'Smart personalized recommendations fetched successfully',
            'detected_favorite_category' => $favoriteType ?? 'No history yet (New User)',
            'data'                       => $recommendedOffers
        ]);

    } catch (Exception $e) {
        return response()->json([
            'status'      => 'success',
            'data'        => offer::where('status', 'active')->where('expiration_time', '>', now())->latest()->take(10)->get(),
            'debug_error' => $e->getMessage()
        ]);
    }
}
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'title' => 'required|string',
                'description' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'quantity_available' => 'required|integer|min:1',
                'original_price' => 'required|numeric',
                'discount_price' => 'required|numeric|lt:original_price',
                'expiration_time' => 'required|date|after:now',
            ]);
            $vendor = Auth::user()->vendor;
            $branch = $vendor->branches()->find($request->branch_id);
            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: This branch does not belong to you!'
                ], 403);
            }
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads'), $imageName);
                $data['image'] = 'uploads/' . $imageName;
            }
            if (isset($data['expiration_time'])) {
                $data['expiration_time'] = date('Y-m-d H:i:s', strtotime($data['expiration_time']));
            }
            $offer = $branch->offers()->create($data);
            $customers = \App\Models\customer::all();
            foreach ($customers as $customer) {
                \App\Models\notification::create([
                    'user_id' => $customer->user_id,
                    'message' => "New Offer from {$vendor->business_name} at branch {$branch->branch_name}: {$offer->title}!",
                    'type' => 'alert',
                    'is_read' => 0,
                ]);
            }
            if ($offer->image) {
                $offer->image = url($offer->image);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Offer created successfully!',
                'offer' => $offer
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Failed!',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong on the server!',
                'error_debug' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
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
            $file = $request->file('image');
            $imageName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads'), $imageName);
            $data['image'] = 'uploads/' . $imageName;
        }

        if (isset($data['expiration_time'])) {
            $data['expiration_time'] = date('Y-m-d H:i:s', strtotime($data['expiration_time']));
        }
        $offer->update($data);

        if ($offer->image) {
            $offer->image = url($offer->image);
        }

        return response()->json(['status' => 'success', 'offer' => $offer]);
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
        try {
            $offer = offer::findOrFail($id);
            $vendor = Auth::user()->vendor;

            if ($offer->branch->vendor_id === $vendor->id) {
                $offer->delete();
                return response()->json(['message' => 'Offer deleted successfully']);
            }

            return response()->json(['message' => 'Unauthorized!'], 403);
        } catch (\Exception $e) {
            // لو حصل أي لغم، هيرجع للفرونت إند السبب الفعلي في ثانية
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while deleting!',
                'error_debug' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
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
                $q->select('id', 'branch_name', 'store_address', 'lat', 'long', 'vendor_id', 'opening_hours', 'contact_phone', 'contact_email');
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
