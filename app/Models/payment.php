<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\order;

class payment extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_id',
        'amount',
        'payment_method',
        'payment_status',
        'payment_details',
        'payment_date',
    ];
    protected $casts = [
        'payment_details' => 'array',
        'payment_date' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(order::class); // كل Payment مرتبط بـ Order
    }
}
