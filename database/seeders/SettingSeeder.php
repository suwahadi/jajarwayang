<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'site_name' => 'CV. Jajar Wayang',
            'site_tagline' => 'Produk & Peralatan CNC Presisi',
            'site_email' => 'jajarwayang25@gmail.com',
            'site_phone' => '085892159266',
            'site_address' => 'Perum Rajeg Asri RT 16/02, Ds Rajeg, Kec. Rajeg, Kab. Tangerang, Banten',
            'origin_district_id' => '73468', // ID destinasi gudang (Komerce) untuk RajaOngkir
            'free_shipping_min' => '0',
            'expiry_order' => '15', // Menit sebelum order kedaluwarsa (otomatis dibatalkan)
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
