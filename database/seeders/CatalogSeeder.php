<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mengisi kategori & produk suku cadang mesin woodworking (panel saw, CNC, edging)
 * berdasarkan DATA_PRODUK.md. Nama merk mesin sengaja tidak dicantumkan (revisi klien).
 *
 * Catatan data:
 *  - Semua produk berstatus "Call" (harga belum dipublikasikan) → original_price = 0.
 *    Storefront menampilkan "Hubungi kami" untuk produk berharga 0.
 *  - Tidak ada produk bervarian pada katalog ini.
 *  - Deskripsi disimpan sebagai HTML rapi (<p> intro + <ul><li> poin kata kunci).
 *  - Kategori "CNC Boring & Motor and Driver" dibuat namun belum berisi produk.
 *
 * Struktur entri produk:
 *  [
 *    'name'   => string,        // nama produk (sudah dirapikan)
 *    'sku'    => string,        // SKU unik (mengacu part number utama)
 *    'weight' => int,           // berat dalam gram
 *    'badge'  => 'new'|'hot'|null,
 *    'intro'  => string|null,   // kalimat pembuka deskripsi (opsional)
 *    'points' => string[],      // poin kata kunci / spesifikasi
 *  ]
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $categoryName => $products) {
            $category = Category::query()->create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'is_active' => true,
            ]);

            foreach ($products as $p) {
                Product::query()->create([
                    'category_id' => $category->id,
                    'name' => $p['name'],
                    'slug' => Str::slug($p['name']),
                    'sku' => $p['sku'],
                    // Produk "Call": harga belum dipublikasikan → 0 (tampil "Hubungi kami").
                    'original_price' => 0,
                    'promo_price' => null,
                    'description' => $this->buildDescription($p['intro'] ?? null, $p['points']),
                    'weight' => $p['weight'],
                    'stock' => fake()->numberBetween(5, 40),
                    'is_active' => true,
                    'badge' => $p['badge'] ?? null,
                ]);
            }
        }
    }

    /**
     * Susun deskripsi HTML rapi: paragraf pembuka (opsional) + daftar poin.
     *
     * @param  list<string>  $points
     */
    private function buildDescription(?string $intro, array $points): string
    {
        $html = '';

        if ($intro !== null && $intro !== '') {
            $html .= '<p>'.e($intro).'</p>';
        }

        $html .= '<ul>';
        foreach ($points as $point) {
            $html .= '<li>'.e($point).'</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function catalog(): array
    {
        return [
            'Panel Saw Sparepart' => [
                [
                    'name' => 'Panel Saw AC Motor 0,4KW 400V 200HZ HSK25R 4-075-01-0553',
                    'sku' => 'JW-4075010553',
                    'weight' => 2500,
                    'badge' => 'hot',
                    'intro' => 'AC Motor 0.4 KW 400 V 200 HZ HSK25R.',
                    'points' => [
                        '4-075-01-0553 motor',
                        '4075010553 motor',
                        'AC motor for edgebanding machine',
                        'Replacement Perske motor',
                        'Spare part for woodworking machine',
                    ],
                ],
                [
                    'name' => 'Panel Saw BK001 Bedienfeld-Kontrol Modul 2-083-02-8340',
                    'sku' => 'JW-2083028340',
                    'weight' => 500,
                    'points' => [
                        '2083028340 PLCS',
                        '2-083-02-8340 controller',
                        'Modul BK001 Bedienfeld-Kontrol',
                        'BK001 2-083-02-8340',
                        'For flat lamination line machine',
                        'Edge bander spare parts',
                    ],
                ],
                [
                    'name' => 'Black Air Flotation Spring Ball Valve for Beam Saw 2-052-66-2600',
                    'sku' => 'JW-2052662600',
                    'weight' => 100,
                    'points' => [
                        '2-052-66-2600 air table valve',
                        'Beamsaw float table valves',
                        'Black valve for beamsaw air table',
                        '2-052-66-1281 air flotation spring ball valve',
                        'Replacement for Part No 2-052-66-2600',
                        'Valve, ball spring loaded ATV/2 & ATV/4 & ATV/3',
                        'Beam saw air table valve',
                        '2052661281 2052662600 ball valve for air table',
                    ],
                ],
                [
                    'name' => 'Beckhoff BK1250 EtherCAT Bus Terminal Coupler 4-086-05-0628',
                    'sku' => 'JW-4086050628',
                    'weight' => 30,
                    'badge' => 'new',
                    'intro' => 'Beckhoff BK1250 EtherCAT to K-Bus terminal coupler.',
                    'points' => [
                        '4-086-05-0628 (4086050628)',
                        'BK1250 EtherCAT',
                        'BK1250 Bus Terminal Coupler',
                        'BK1250 EtherCAT to K-Bus coupler',
                        'BK1250 I/O coupler',
                        'Terminal coupler',
                        'Compact coupler between EtherCAT Terminals and Bus Terminals',
                    ],
                ],
                [
                    // Gabungan Produk 5 + Produk 6 pada DATA_PRODUK.md (nama & deskripsi
                    // terpisah pada sumber; sebenarnya satu produk yang sama).
                    'name' => 'KEB F5 Inverter 12F5A3D-YA18 for Panel Saw HPL330/180 4-008-39-1923',
                    'sku' => 'JW-4008391923',
                    'weight' => 2000,
                    'intro' => 'KEB Combivert F5 frequency inverter 12F5A3D-YA18 untuk panel saw HPL330/180.',
                    'points' => [
                        'KEB 12F5A3D-YA18',
                        'KEB VFD frequency converter 12F5A3D-YA18',
                        'KEB 3HP 12F5A3D-YA18',
                        '4008391923 frequency converter',
                        '4-008-39-1923 regulator power supply',
                        '4.008.39.1923 regulator 12.F5.A3D-YA18',
                        'Drive 12F5A3D-YA18',
                        'Parts of panel saw HPL330/180',
                    ],
                ],
                [
                    'name' => 'Rexroth Servo Motor MSM019A-0300-NN-M0-CH1 R911325128',
                    'sku' => 'JW-R911325128',
                    'weight' => 1000,
                    'points' => [
                        'MSM019A-0300-NN-M0-CH1',
                        'R911325128',
                        'Rexroth servo motor',
                        'Edgebander servo motor',
                        'KAL370 motor',
                        '0-200-09-1975 motor',
                        'Rexroth R911325128',
                        'Bosch MSM019A0300NNM0CH1',
                    ],
                ],
                [
                    'name' => 'Hiwin Linear Guide Rail HGR25 with Linear Guide Block HGH25CA',
                    'sku' => 'JW-HGR25-HGH25CA',
                    'weight' => 1000,
                    'intro' => 'Komponen gerak linier: rel pemandu Hiwin HGR25 dengan blok HGH25CA.',
                    'points' => [
                        'HGH25CA linear block',
                        'HGR25R linear guide rail',
                        'Hiwin HGH25CA square carriage',
                        'Slides blocks HGH25CA',
                        'HGR series linear guide rail',
                        'CNC mechanical guide',
                        'Linear guide slider',
                        'Linear motion components',
                    ],
                ],
                [
                    'name' => 'Wenglor OPT292 Photoelectric Switch Sensor for KAL211 4-008-61-1344',
                    'sku' => 'JW-4008611344',
                    'weight' => 200,
                    'points' => [
                        '4-008-61-1344 photoelectric switch',
                        'Wenglor OPT292',
                        '4008611344 OPT 292',
                        '4008611344 VGA laser optical sensor',
                        'OPT292 reflex sensor',
                        'Wenglor OPT292 reflex push button',
                        '4-008-61-1344 reflecting fiber-optic key',
                        'Switch for KAL211',
                    ],
                ],
                [
                    'name' => 'SICK WT150-P460 Miniature Photoelectric Sensor',
                    'sku' => 'JW-WT150P460',
                    'weight' => 200,
                    'points' => [
                        'WT150-P460 SICK',
                        'Miniature photoelectric sensors',
                        'WT150-P460 photoelectric proximity sensor',
                        'Part number: 6011050',
                        'SICK WT150-P460 photo proximity sensor',
                        'SICK proximity sensor',
                        'LED sensor',
                        'SICK WT150-P460 photocell',
                    ],
                ],
                [
                    'name' => 'Yaskawa SGD7S-5R5A00B202 Servo Drive 750W Servopack (ex SGD7S-5R5A00A002)',
                    'sku' => 'JW-SGD7S5R5A00B202',
                    'weight' => 1500,
                    'badge' => 'hot',
                    'intro' => 'Yaskawa Sigma-7 Servopack SGD7S-5R5A00B202, AC servo drive 750W.',
                    'points' => [
                        'Model No: SGD7S-5R5A00B202',
                        'Old Model No: SGD7S-5R5A00A002',
                        'Sigma 7 Series SGD7S-5R5A00B202',
                        'Yaskawa Servopack amplifier',
                        'Yaskawa servo drive',
                        'Yaskawa 750W AC servo driver',
                        'Yaskawa single axis servo driver',
                        'SGD7S driver',
                    ],
                ],
                [
                    'name' => 'Panasonic MBDHT2510E A5 400W Servo Driver MINAS A5 Series',
                    'sku' => 'JW-MBDHT2510E',
                    'weight' => 1000,
                    'points' => [
                        'Panasonic MBDHT2510E',
                        'AC servo motor driver MBDHT2510E',
                        'MBDHT2510E Panasonic A5 400W servo driver',
                        'Panasonic servo driver MBDHT2510E',
                        'MBDHT2510E Panasonic AC servo driver',
                        'Minas A5 Series - Panasonic',
                        'AC servo driver Minas A5',
                        'Minas A5 family servo driver',
                    ],
                ],
                [
                    'name' => 'Mitsubishi MDS-B-SVJ2-10 Servo Drive 1KW Servo Amplifier (Mazak Meldas)',
                    'sku' => 'JW-MDSBSVJ210',
                    'weight' => 1000,
                    'points' => [
                        'MDS-B-SVJ2-10 servo drive',
                        'Meldas AC servo MDS-B-SVJ2',
                        'MDS-B-SVJ2 200-230V',
                        'MDS-B-SVJ2-10 Mitsubishi servo drive unit',
                        'Mitsubishi Servopack amplifier',
                        '1kw servo amplifier',
                        'Mitsubishi servo driver MDS-B-SVJ2-10',
                        'MDS-B-SVJ2 series servo driver',
                    ],
                ],
            ],

            'CNC Sparepart' => [
                [
                    'name' => 'Conveyor Chain Track Pad 80x62 for Edgebander E1711E0001',
                    'sku' => 'JW-E1711E0001',
                    'weight' => 200,
                    'badge' => 'new',
                    'points' => [
                        'E1711E0001 80x62mm chain pads',
                        'Conveyor pad E1711E0001 for edgebander',
                        'Feed chain track pads 80 x 62mm',
                        'Chain pads for edge banding machine',
                        'Track pad for edgebander machines',
                    ],
                ],
                [
                    'name' => 'White Air Table Valve for Float Table L9402403100',
                    'sku' => 'JW-L9402403100',
                    'weight' => 100,
                    'points' => [
                        'L9402403100 air table valve',
                        'Float table valves (14 x 15mm)',
                        'White insert valve air pillow table',
                        'Shut-off valve desktop',
                        'Air ball infeed table for dividing saw',
                        'Beam saw air table valve',
                        'Ball valve set for air table',
                        'Ball bearing air cushion',
                    ],
                ],
            ],

            'Edging Sparepart' => [
                [
                    'name' => 'Carbide Knives Insert 16.6x16x2 R2 for Edgebander 0373367000E 0373365800A',
                    'sku' => 'JW-0373367000E',
                    'weight' => 100,
                    'points' => [
                        'Carbide knives for edgebanders',
                        '0373367000E knife',
                        '0373365800A blade',
                        'Radius inserts for edgebander',
                        'Tools for ME 25 edgebander',
                        'Edge banding machine tools',
                        '0373365800A',
                        '0373367000E',
                    ],
                ],
                [
                    'name' => 'Upper Rubber Sealing Suction Cup 114x54x18mm for Vacuum Block 0387540039H',
                    'sku' => 'JW-0387540039H',
                    'weight' => 100,
                    'points' => [
                        '0387540039H suction cups',
                        'Ø114 x Ø54 x 18 mm round upper seals',
                        'Upper sealing rubber D = 114 x 18 mm',
                        'Upper sealing rubber for vacuum block',
                        'Rubber suction cup upper part for 2990340029F',
                        'Joint ventouse round Ø114',
                        'Replacement suction plates Ø114 for CNC',
                    ],
                ],
                [
                    'name' => 'Yaskawa SGMAH-03DAA61 AC Servo Motor 0001335103F',
                    'sku' => 'JW-0001335103F',
                    'weight' => 500,
                    'badge' => 'new',
                    'intro' => 'Brand new original 0001335103F / Yaskawa SGMAH-03DAA61 AC servo motor.',
                    'points' => [
                        '0001335103F servo motor',
                        'Yaskawa SGMAH-03DAA61 servo motor',
                        'Yaskawa AC servo motor',
                        '400V 1.3A 3000rpm 300W',
                        'Part number 0001335103F',
                        'Yaskawa model SGMAH-03DAA61',
                    ],
                ],
                [
                    'name' => 'Track Pad 63x37mm for Edgebander 0533716039A + 1433716039L + 0333716046A',
                    'sku' => 'JW-0533716039A',
                    'weight' => 300,
                    'points' => [
                        '63x37mm track pad for edgebander',
                        'K201 / K203 / K208 chain pads',
                        '0533716039A rubber pad',
                        '1433716039L nylon chain link',
                        '0333716046A track pad pin',
                        'Spare track pad',
                        'Edgebander conveyor pad',
                    ],
                ],
                [
                    'name' => 'Toothed Timing Belt 100T10-4540 for Edgebander 07L0035522E',
                    'sku' => 'JW-07L0035522E',
                    'weight' => 500,
                    'points' => [
                        '100T10-4540 toothed timing belt',
                        '07L0035522E belt',
                        'Timing belt for K230',
                        '100T10-4540 belt',
                        'Synchronous belts for edgebander',
                        'Edgebander conveyor belt',
                        'Replacement belts',
                    ],
                ],
            ],

            // Belum ada produk pada DATA_PRODUK.md untuk kategori ini.
            'CNC Boring & Motor and Driver' => [],
        ];
    }
}
