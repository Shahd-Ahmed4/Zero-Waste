<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\User;
use App\Models\Admin;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $admins = Admin::all();

        foreach (User::where('role','customer')->get() as $user) {
            Customer::create([
                'user_id' => $user->id,
                'admin_id' => $admins->random()->id
            ]);
        }
    }
}