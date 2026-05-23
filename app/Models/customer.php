<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\admin;
use App\Models\review;
use App\Models\order;

class customer extends Model
{
    protected $fillable = ['user_id', 'admin_id'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function approvedby()
    {
        return $this->belongsTo(admin::class, 'admin_id');
    }
    public function order()
    {
        return $this->hasMany(order::class);
    }
    public function reviews()
    {
        return $this->hasMany(review::class);
    }
}
