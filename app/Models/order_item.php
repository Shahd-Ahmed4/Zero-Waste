<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\order;
use App\Models\offer;

class order_item extends Model
{
    protected $fillable=[
        'order_id',
        'offer_id',
        'original_price', // ضيفي دي هنا
        'price',
        'quantity'
    ];
    public function order(){
        return $this->belongsTo(order::class);
    }
    public function offer(){
        return $this->belongsTo(offer::class);
    }
}
