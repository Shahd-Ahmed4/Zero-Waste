<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\review;
use App\Models\branch;
use App\Models\order_item;


class offer extends Model
{
    use SoftDeletes; // 2. استخدمي الخاصية دي جوه الكلاس

    // ... باقي الكود بتاعك (fillable, relations)
    protected $fillable = [
        'branch_id',
        'title',
        'description',
        'image',
        'quantity_available',
        'original_price',
        'discount_price',
        'expiration_time',
        'status',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    // السطر ده بيخلي الـ Average Rating يظهر في الـ API أوتوماتيك
    protected $appends = ['average_rating', 'image_url'];

    public function branch()
    {
        return $this->belongsTo(branch::class); // كل Offer مرتبط بـ branch واحد
    }
    // تقدري برضه توصلي للـ Vendor صاحب العرض ده بسهولة
    public function vendor()
    {
        return $this->branch ? $this->branch->vendor : null;
    }

    public function orderitem()
    {
        return $this->hasMany(order_item::class);
    }
    public function reduceStock($quantity)
    {
        if ($this->quantity_available < $quantity) {
            throw new \Exception("Insufficient stock for offer: {$this->title}");
        }

        return $this->decrement('quantity_available', $quantity);
    }

    public function restoreStock($quantity)
    {
        return $this->increment('quantity_available', $quantity);
    }
    public function reviews()
    {
        return $this->hasMany(review::class, 'offer_id');
    }

    // الـ Accessor اللي بيحسب النجوم (⭐)
    public function getAverageRatingAttribute()
    {
        // لو الكنترولر حاسب الـ Avg مسبقاً في كويري واحد مجمع، بنستخدمه فوراً ونوفر وقت
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            return round($this->attributes['reviews_avg_rating'] ?: 0, 1);
        }

        // لو الـ Controller محسبهاش (زي صفحة الـ Show لتفاصيل عرض واحد)، بيحسبها عادي من الداتابيز
        return round($this->reviews()->avg('rating') ?: 0, 1);
    }
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                return $this->image;
            }

            // 2. لو المسار جوه الـ uploads الجديد (بتاعنا المباشر)
            if (str_starts_with($this->image, 'uploads/')) {
                return asset($this->image);
            }

            // 3. ده عشان الصور القديمة اللي لسه متخزنة جوه الـ storage ومتمسحتش تشتغل برضه
            return asset('storage/' . $this->image);
        }

        // صورة افتراضية لو مفيش صورة للعرض
        return asset('images/default-placeholder.png');
    }
}
