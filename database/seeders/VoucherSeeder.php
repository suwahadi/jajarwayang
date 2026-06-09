<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'code' => 'CNCHEMAT10',
                'discount_type' => VoucherType::PERCENTAGE,
                'discount_value' => 10,
                'min_purchase' => 500000,
                'max_usage' => 100,
            ],
            [
                'code' => 'ONGKIR50K',
                'discount_type' => VoucherType::FIXED,
                'discount_value' => 50000,
                'min_purchase' => 250000,
                'max_usage' => 0, // tak terbatas
            ],
            [
                'code' => 'PRESISI15',
                'discount_type' => VoucherType::PERCENTAGE,
                'discount_value' => 15,
                'min_purchase' => 1000000,
                'max_usage' => 50,
            ],
        ];

        foreach ($vouchers as $data) {
            Voucher::query()->updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'used_count' => 0, 'valid_until' => now()->addDays(60)],
            );
        }
    }
}
