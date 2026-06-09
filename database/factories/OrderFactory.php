<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 1000000);
        $shippingCost = fake()->numberBetween(10000, 50000);

        return [
            'order_number' => 'JW-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('08##########'),
            'shipping_province_id' => null,
            'shipping_city_id' => null,
            'shipping_district_id' => fake()->numberBetween(10000, 20000),
            'shipping_destination_label' => fake()->city(),
            'shipping_address' => fake()->address(),
            'shipping_courier' => fake()->randomElement(['jne', 'pos', 'tiki']),
            'shipping_cost' => $shippingCost,
            'voucher_id' => null,
            'discount_amount' => 0,
            'subtotal' => $subtotal,
            'grand_total' => $subtotal + $shippingCost,
            'status' => OrderStatus::PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
