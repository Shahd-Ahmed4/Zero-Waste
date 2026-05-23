<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\customer;
use App\Models\offer;

class review extends Model
{
    protected $fillable = ['customer_id', 'offer_id', 'rating', 'comment', 'image', 'is_visible'];
    protected $appends = ['image_url'];

    // العلاقة مع اليوزر (اللي عمل التقييم)
    public function customer()
    {
        return $this->belongsTo(customer::class);
    }

    // العلاقة مع المحل
    public function offer()
    {
        return $this->belongsTo(offer::class);
    }
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

}
