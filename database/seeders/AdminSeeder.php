<?php

namespace Database\Seeders;
use App\Models\admin;
use Illuminate\Database\Seeder;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $index => $user) {
            admin::create([
                'user_id' => $user->id,
                // نفس كودك السريع بس متعدل لـ 3 مستويات
                'permission_level' => $index == 0 ? 'super_admin' : ($index == 1 ? 'manager' : 'support')
            ]);
        }
    }
}