<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mengisi kategori & produk suku cadang mesin woodworking (Homag, Biesse, SCM)
 * berdasarkan DATA_PRODUK.md.
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
            'Homag Sparepart' => [
                [
                    'name' => 'Homag AC Motor 0,4KW 400V 200HZ HSK25R 4-075-01-0553',
                    'sku' => 'JW-4075010553',
                    'weight' => 2500,
                    'badge' => 'hot',
                    'intro' => 'Homag AC Motor 0.4 KW 400 V 200 HZ HSK25R.',
                    'points' => [
                        '4-075-01-0553 Homag motor',
                        'Homag AC motor',
                        '4075010553 Homag motor',
                        'AC motor for Homag edgebanding machine',
                        'Homag 4-075-01-0553 motor',
                        'Replacement Perske motor',
                        'Spare part for Homag machine',
                    ],
                ],
                [
                    'name' => 'Homag BK001 Bedienfeld-Kontrol Modul 2-083-02-8340',
                    'sku' => 'JW-2083028340',
                    'weight' => 500,
                    'points' => [
                        '2083028340 Homag PLCS',
                        '2-083-02-8340 Homag controller',
                        'Modul BK001 Bedienfeld-Kontrol',
                        'Homag BK001 2-083-02-8340',
                        'For flat lamination line machine',
                        'Homag spare parts',
                        'Edge bander spare parts',
                    ],
                ],
                [
                    'name' => 'Black Air Flotation Spring Ball Valve for Homag Holzma Beam Saw 2-052-66-2600',
                    'sku' => 'JW-2052662600',
                    'weight' => 100,
                    'points' => [
                        'Homag 2-052-66-2600 air table valve',
                        'Holzma beamsaw float table valves',
                        'Black valve for beamsaw air table',
                        '2-052-66-1281 air flotation spring ball valve',
                        'Replacement for Homag Part No 2-052-66-2600',
                        'Valve, ball spring loaded ATV/2 & ATV/4 & ATV/3',
                        'Holzma beam saw air table valve',
                        '2052661281 2052662600 ball valve for Homag air table',
                    ],
                ],
                [
                    'name' => 'Homag Beckhoff BK1250 EtherCAT Bus Terminal Coupler 4-086-05-0628',
                    'sku' => 'JW-4086050628',
                    'weight' => 30,
                    'badge' => 'new',
                    'intro' => 'Beckhoff BK1250 EtherCAT to K-Bus terminal coupler.',
                    'points' => [
                        'Homag 4-086-05-0628 (4086050628)',
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
                    'name' => 'Homag KEB F5 Inverter 12F5A3D-YA18 for Holzma Panel Saw HPL330/180 4-008-39-1923',
                    'sku' => 'JW-4008391923',
                    'weight' => 2000,
                    'intro' => 'KEB Combivert F5 frequency inverter 12F5A3D-YA18 untuk Homag Holzma panel saw HPL330/180.',
                    'points' => [
                        'KEB 12F5A3D-YA18',
                        'KEB Homag VFD frequency converter 12F5A3D-YA18',
                        'KEB 3HP 12F5A3D-YA18',
                        'Homag 4008391923 frequency converter',
                        '4-008-39-1923 regulator power supply',
                        '4.008.39.1923 regulator 12.F5.A3D-YA18',
                        'Homag drive 12F5A3D-YA18',
                        'Parts of Holzma panel saw HPL330/180',
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
                        'Homag edgebander servo motor',
                        'KAL370 Ambition 2270 motor',
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
                    'name' => 'Wenglor OPT292 Photoelectric Switch Sensor for Homag KAL211 4-008-61-1344',
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
                        'Switch for Homag KAL211',
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

            'Biesse Sparepart' => [
                [
                    'name' => 'Conveyor Chain Track Pad 80x62 for Biesse Akron Artech Roxyl Edgebander E1711E0001',
                    'sku' => 'JW-E1711E0001',
                    'weight' => 200,
                    'badge' => 'new',
                    'points' => [
                        'E1711E0001 80x62mm chain pads',
                        'Conveyor pad E1711E0001 for Biesse edgebander',
                        'Biesse feed chain track pads 80 x 62mm',
                        'Track pads for Biesse Roxyl edgebander',
                        'Biesse Akron 650 chain pad',
                        'Conveyor pad for Biesse Polymac Ergho 5 machine',
                        'Chain pads for Biesse Artech edge banding machine',
                        'Track pad for Biesse, Selco & RBO edgebander machines',
                    ],
                ],
                [
                    'name' => 'White Air Table Valve for Biesse Selco Float Table L9402403100',
                    'sku' => 'JW-L9402403100',
                    'weight' => 100,
                    'points' => [
                        'Biesse L9402403100 air table valve',
                        'Biesse Selco float table valves (14 x 15mm)',
                        'White insert valve air pillow table for Biesse',
                        'Shut-off valve desktop for Biesse',
                        'Air ball infeed table for dividing saw',
                        'Biesse Selco air table valve',
                        'Selco beam saw air table valve',
                        'Ball valve set for Biesse Selco air table',
                        'Ball bearing air cushion',
                    ],
                ],
            ],

            'SCM Sparepart' => [
                [
                    'name' => 'SCM Carbide Knives Insert 16.6x16x2 R2 for Edgebander 0373367000E 0373365800A',
                    'sku' => 'JW-0373367000E',
                    'weight' => 100,
                    'points' => [
                        'Carbide knives for SCM edgebanders',
                        'SCM 0373367000E knife',
                        'SCM 0373365800A blade',
                        'Radius inserts for SCM edgebander',
                        'Tools for SCM Minimax ME 25',
                        'Edge banding machine tools',
                        '0373365800A',
                        '0373367000E',
                    ],
                ],
                [
                    'name' => 'SCM Morbidelli Upper Rubber Sealing Suction Cup 114x54x18mm 0387540039H',
                    'sku' => 'JW-0387540039H',
                    'weight' => 100,
                    'points' => [
                        '0387540039H SCM suction cups',
                        'Ø114 x Ø54 x 18 mm round upper seals for SCM Morbidelli',
                        'SCM upper sealing rubber D = 114 x 18 mm',
                        'Upper sealing rubber for vacuum block SCM',
                        'Rubber suction cup upper part for 2990340029F',
                        'Joint ventouse round Ø114',
                        'Replacement suction plates Ø114 for Morbidelli U CNC',
                        'Round 114mm dia. rubber pad for SCM Tech machines',
                    ],
                ],
                [
                    'name' => 'SCM Yaskawa SGMAH-03DAA61 AC Servo Motor 0001335103F',
                    'sku' => 'JW-0001335103F',
                    'weight' => 500,
                    'badge' => 'new',
                    'intro' => 'Brand new original SCM 0001335103F / Yaskawa SGMAH-03DAA61 AC servo motor.',
                    'points' => [
                        'SCM 0001335103F servo motor',
                        'Yaskawa SGMAH-03DAA61 servo motor',
                        'Yaskawa AC servo motor',
                        '400V 1.3A 3000rpm 300W',
                        'SCM servo motor',
                        'SCM part number 0001335103F',
                        'Yaskawa model SGMAH-03DAA61',
                        'Yaskawa servo motor for SCM machine',
                    ],
                ],
                [
                    'name' => 'Track Pad 63x37mm for SCM Olimpic Edgebander 0533716039A + 1433716039L + 0333716046A',
                    'sku' => 'JW-0533716039A',
                    'weight' => 300,
                    'points' => [
                        '63x37mm track pad for SCM Olimpic',
                        'SCM Olimpic K201 / K203 / K208 chain pads',
                        'Track pad for SCM – 63x37mm',
                        '0533716039A rubber pad',
                        '1433716039L nylon chain link',
                        '0333716046A SCM track pad pin',
                        'Spare SCM track pad',
                        'SCM Olimpic edgebander conveyor pad',
                    ],
                ],
                [
                    'name' => 'SCM Toothed Timing Belt 100T10-4540 for Edgebander 07L0035522E',
                    'sku' => 'JW-07L0035522E',
                    'weight' => 500,
                    'points' => [
                        '100T10-4540 toothed timing belt',
                        'SCM 07L0035522E belt',
                        'Timing belt for SCM Olimpic K230',
                        'SCM toothed belt',
                        '100T10-4540 belt',
                        'Synchronous belts for SCM edgebander',
                        'SCM edgebander conveyor belt',
                        'Replacement belts for SCM',
                    ],
                ],
            ],

            // Belum ada produk pada DATA_PRODUK.md untuk kategori ini.
            'CNC Boring & Motor and Driver' => [],
        ];
    }
}
