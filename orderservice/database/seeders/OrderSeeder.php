<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;

class OrderSeeder extends Seeder
{
    /**
     * Seed dummy data untuk tabel orders.
     */
    public function run(): void
    {
        $orders = [
            [
                'user_id'     => 1,
                'product_id'  => 1,
                'quantity'    => 2,
                'total_price' => 50000,
                'status'      => 'pending',
            ],
            [
                'user_id'     => 2,
                'product_id'  => 3,
                'quantity'    => 1,
                'total_price' => 25000,
                'status'      => 'processing',
            ],
            [
                'user_id'     => 1,
                'product_id'  => 2,
                'quantity'    => 3,
                'total_price' => 75000,
                'status'      => 'completed',
            ],
            [
                'user_id'     => 3,
                'product_id'  => 1,
                'quantity'    => 1,
                'total_price' => 25000,
                'status'      => 'cancelled',
            ],
        ];

        foreach ($orders as $order) {
            Order::create($order);
        }
    }
}
