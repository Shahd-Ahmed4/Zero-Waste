<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 👑 Admins (3) -> كلهم Active دائماً
        User::factory()->count(3)->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        // 🏪 Vendors (80) -> الأغلبية العظمى Active و Pending، مع عدد قليل جداً Rejected و Blocked
        User::factory()->count(80)->create([
            'role' => 'vendor',
            'status' => function () {
                return fake()->randomElement([
                    'active',
                    'active',
                    'active',
                    'active',
                    'active', // وزن أعلى لـ Active (الأغلبية العظمى)
                    'pending',
                    'pending',                             // وزن متوسط لـ Pending (قيد المراجعة)
                    'rejected',                                       // وزن منخفض لـ Rejected (مرفوض)
                    'blocked'                                         // وزن منخفض لـ Blocked (محظور)
                ]);
            }
        ]);

        // 🧑‍💻 Customers (100) -> دايماً Active، مع احتمالية ظهور كام مستخدم Blocked فقط
        User::factory()->count(100)->create([
            'role' => 'customer',
            'status' => function () {
                return fake()->randomElement([
                    'active',
                    'active',
                    'active',
                    'active',
                    'active',
                    'active',
                    'active',
                    'active',
                    'active',
                    'blocked' // نسبة 10% فقط احتمال يكون الحساب Blocked والباقي Active دائمًا
                ]);
            }
        ]);
    }
}