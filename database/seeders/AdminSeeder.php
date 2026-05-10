<?php

namespace Database\Seeders;
use App\Models\admin;
use Illuminate\Database\Seeder;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $admins = User::where('role','admin')->get();

        foreach ($admins as $index => $user) {
            admin::create([
                'user_id' => $user->id,
                'permission_level' => $index == 0 ? 'super_admin' : 'manager'
            ]);
        }
    }
}