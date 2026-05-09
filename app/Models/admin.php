<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\customer;
use App\Models\vendor;

class admin extends Model
{
    protected $fillable=['user_id','permission_level'];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function supervisedcustomer(){
        return $this->hasMany(customer::class,'admin_id');
    }
    public function supervisedvendor(){
        return $this->hasMany(vendor::class,'admin_id');
    }
}
