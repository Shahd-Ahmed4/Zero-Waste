<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run()
    {
        // بنجيب كل المستخدمين اللي دورهم عملاء
        $customers = \App\Models\Customer::all();        // بنجيب كل الفروع ومعاها الـ vendor بتاعها
        $branches = Branch::with('vendor')->get();

        if ($customers->isEmpty() || $branches->isEmpty()) {
            return;
        }

        // إنشاء 50 طلب عشوائي
        for ($i = 0; $i < 50; $i++) {
            $customer = $customers->random();
            $branch = $branches->random();

            $deliveryType = fake()->randomElement(['delivery', 'pickup']);
            $deliveryFee = ($deliveryType === 'delivery') ? rand(20, 50) : 0;
            $subtotal = rand(150, 1000);

            Order::create([
                'customer_id' => $customer->id,
                'vendor_id' => $branch->vendor_id,
                'branch_id' => $branch->id,
                'order_status' => fake()->randomElement(['pending', 'processing', 'completed', 'in_transit', 'delivered', 'cancelled']),
                'delivery_type' => $deliveryType,
                'delivery_address' => ($deliveryType === 'delivery') ? fake()->address() : null,
                'delivery_fees' => $deliveryFee,
                'total_amount' => $subtotal + $deliveryFee,
                'payment_method' => fake()->randomElement(['cash', 'card']),
                'order_date' => Carbon::now()->subDays(rand(0, 30)), // طلبات خلال آخر شهر
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}