<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\vendor;
use App\Models\offer;

class branch extends Model
{
    protected $fillable = [
        'vendor_id',
        'branch_name',
        'opening_hours',
        'store_address',
        'contact_email',
        'contact_phone',
        'lat',
        'long',
        'status'
    ];
    protected $hidden = ['id','vendor_id', 'created_at', 'updated_at'];

    // الفرع يتبع Vendor واحد
    public function vendor()
    {
        return $this->belongsTo(vendor::class);
    }

    // الفرع الواحد عنده كذا عرض (Offer)
    public function offers()
    {
        return $this->hasMany(offer::class);
    }
    public function orders()
    {
        return $this->hasMany(order::class);
    }
}
