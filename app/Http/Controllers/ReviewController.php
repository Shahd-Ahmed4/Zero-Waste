<?php

namespace App\Http\Controllers;

use App\Models\order;
use App\Models\review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        try {
            // 1. Validation for the incoming request
            $request->validate([
                'offer_id' => 'required|exists:offers,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
            ]);

            // Get the Customer profile associated with the currently logged-in User
            $customer = \App\Models\customer::where('user_id', auth()->id())->first();

            if (!$customer) {
                return response()->json([
                    'message' => 'This user does not have a customer account.'
                ], 404);
            }

            $customerId = $customer->id;
            $offerId = $request->offer_id;

            // 2. Verification: Did this Customer purchase this specific Offer and is the Order completed?
            $hasBought = order::where('customer_id', $customerId)
                ->where('order_status', 'completed')
                ->whereHas('items', function ($query) use ($offerId) {
                    $query->where('offer_id', $offerId);
                })
                ->exists();

            if (!$hasBought) {
                return response()->json([
                    'message' => 'Access denied. You can only review offers you have successfully purchased and received.'
                ], 403);
            }

            // 3. Duplicate Check: Has the customer already reviewed this offer?
            $alreadyReviewed = review::where('customer_id', $customerId)
                ->where('offer_id', $offerId)
                ->exists();

            if ($alreadyReviewed) {
                return response()->json([
                    'message' => 'You have already submitted a review for this offer.'
                ], 400);
            }
            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads'), $imageName);
                $imagePath = 'uploads/' . $imageName;
            }

            // 4. Save the review using the customer_id
            $review = review::create([
                'customer_id' => $customerId,
                'offer_id' => $offerId,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'image' => $imagePath,  // تخزين مسار الصورة
                'is_visible' => true,        // بيظهر أوتوماتيك إلا لو الأدمن أخفاه
            ]);
            if ($review->image) {
                $review->image = asset($review->image);
            }

            return response()->json([
                'message' => 'Review submitted successfully! Thank you for your feedback.',
                'data' => $review
            ], 201);
        } catch (\Exception $e) {
            // 🟢 هنا السر! لو حصل أي Error السيرفر هيرجعلك مكانه بالظبط بدل الـ 500 العمياء
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong inside the review code!',
                'error_message' => $e->getMessage(), // هيقولك إيه الغلط بالظبط
                'file' => $e->getFile(),             // هيقولك اسم الملف اللي فيه المشكلة
                'line' => $e->getLine()              // هيجيبلك رقم السطر اللي ضرب!
            ], 500);
        }
    }
    public function myReviews()
    {
        // بنجيب الكاستمر المرتبط باليوزر
        $customer = Auth::user()->customer;

        if (!$customer) {
            return response()->json([
                'message' => 'Unauthorized. Customer profile not found.'
            ], 403);
        }

        // بنجيب كل الريفيوهات بتاعته مع بيانات العرض (Offer) اللي اتقيم
        $reviews = review::with('offer:id,title')
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        return response()->json([
            'count' => $reviews->count(),
            'data' => $reviews
        ], 200);
    }

    // Display reviews for a specific offer
    public function index($offer_id)
    {
        $reviews = review::with('customer.user:id,name')
            ->where('offer_id', $offer_id)
            ->where('is_visible', true)
            ->latest()
            ->get();

        // 🟢 خطوة إضافية: عشان نضمن إن روابط الصور ترجع كاملة بـ الـ URL بتاع السيرفر للفرونت إند
        $reviews->transform(function ($review) {
            if ($review->image) {
                $review->image = asset($review->image);
            }
            return $review;
        });

        return response()->json($reviews);
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy($id)
    {
        // 1. نجيب الكاستمر اللي عامل Login حالياً
        $customer = Auth::user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Unauthorized. Customer profile not found.'], 403);
        }

        // 2. ندور على الريفيو ونتأكد إنه ملك للكاستمر ده بالذات
        $review = review::where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();

        // 3. لو مش موجود أو مش بتاعه
        if (!$review) {
            return response()->json([
                'message' => 'Review not found or you do not have permission to delete it.'
            ], 404);
        }

        // 4. المسح
        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.'
        ], 200);
    }

}
