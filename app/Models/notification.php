<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class notification extends Model
{
    protected $fillable=[
        'user_id',
        'is_read',
        'message',
        'type',
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
