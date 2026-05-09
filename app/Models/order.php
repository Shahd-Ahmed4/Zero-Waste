<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\customer;
use App\Models\order_item;
use App\Models\payment;

class order extends Model
{
    protected $fillable = [
        'customer_id',
        'vendor_id',
        'branch_id',
        'order_status',
        'delivery_type',   // ضيفي ده
        'delivery_address',// ضيفي ده
        'delivery_fees',   // ضيفي ده
        'total_amount',
        'payment_method',
        'order_date'
    ];
    protected $casts = [
        'order_date' => 'datetime', // عشان تقدري تستخدمي عليه functions التاريخ
    ];
    public function customer()
    {
        return $this->belongsTo(customer::class);
    }
    public function items()
    {
        return $this->hasMany(order_item::class);
    }
    public function payment()
    {
        return $this->hasOne(payment::class);
    }
    public function vendor()
    {
        return $this->belongsTo(vendor::class);
    }

    public function branch()
    {
        return $this->belongsTo(branch::class);
    }
}
