<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\User;
use App\Models\admin;
use App\Models\branch;
use App\Models\offer;
use App\Models\customer;


class vendor extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'business_name',
        'logo',
        'vendor_type',
        'rejection_reason',
        'tax_number',
        'commercial_register',
        'tax_card',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn($value) => str_starts_with($value, 'http') ? $value : asset('storage/' . $value),
        );
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function approvedby()
    {
        return $this->belongsTo(admin::class, 'admin_id');
    }
    // لو عايزة توصلي لكل الـ Offers بتاعة الـ Vendor من كل فروعه (اختياري بس مفيد)
    public function offers()
    {
        return $this->hasManyThrough(offer::class, branch::class);
    }
    public function branches()
    {
        return $this->hasMany(branch::class);
    }
    public function orders()
    {
        return $this->hasMany(order::class);
    }
    public function favoritedByCustomers()
    {
        // الفيندور ممكن يكون موجود في مفضلة كذا زبون
        return $this->belongsToMany(customer::class, 'favorites', 'vendor_id', 'customer_id')->withTimestamps();
    }
}
