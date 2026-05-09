<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $admins = User::where('role','admin')->get();

        foreach ($admins as $index => $user) {
            Admin::create([
                'user_id' => $user->id,
                'permission_level' => $index == 0 ? 'super_admin' : 'manager'
            ]);
        }
    }
}