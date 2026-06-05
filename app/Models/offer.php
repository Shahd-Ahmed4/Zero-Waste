<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\review;
use App\Models\branch;
use App\Models\order_item;


class offer extends Model
{
    use SoftDeletes; 

    
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
   
    protected $appends = ['average_rating', 'image_url', 'status'];

    protected $casts = [
        'expiration_time' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('activeOffersForCustomers', function ($builder) {
            
            if (app()->runningInConsole() || (auth()->check() && (auth()->user()->vendor || auth()->user()->role === 'admin'))) {
                return;
            }

            
            $builder->where('offers.status', 'active')
                ->where('offers.quantity_available', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('offers.expiration_time')
                        ->orWhere('offers.expiration_time', '>', now());
                });
        });
    }

    public function branch()
    {
        return $this->belongsTo(branch::class); 
    }
    
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

        
        $this->decrement('quantity_available', $quantity);

        
        $this->refresh();

        return true;
    }

    public function restoreStock($quantity)
    {
       
        $this->increment('quantity_available', $quantity);

        
        $this->refresh();
    }
    public function reviews()
    {
        return $this->hasMany(review::class, 'offer_id');
    }

    
    public function getAverageRatingAttribute()
    {
        
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            return round($this->attributes['reviews_avg_rating'] ?: 0, 1);
        }

        
        return round($this->reviews()->avg('rating') ?: 0, 1);
    }
    public function getImageUrlAttribute()
    {
        
        if (!$this->image) {
            return asset('images/default-placeholder.png');
        }

        
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

       
        if (str_starts_with($this->image, 'uploads/')) {
            return url($this->image); 
        }

        return asset('storage/' . $this->image);
    }
    public function getStatusAttribute()
    {
        if ($this->quantity_available <= 0) {
            return 'disabled';
        }

        if ($this->expiration_time && $this->expiration_time->isPast()) {
            return 'expired';
        }

        return $this->attributes['status'] ?? 'active';
    }
}
