<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper RajaOngkir (Komerce) tipe FREE/Starter.
 *
 * API gratis Komerce TIDAK menyediakan cascade provinsi/kota/kecamatan;
 * sebagai gantinya memakai satu endpoint pencarian destinasi (subdistrict)
 * yang mengembalikan `id` + `label` lengkap, lalu kalkulasi ongkir memakai
 * id tersebut. Base URL & API key diambil dari config; titik asal gudang
 * dibaca dari setting `origin_district_id` (tabel `settings`) agar dapat
 * diubah admin tanpa menyentuh `.env`.
 *
 * Endpoint:
 *  - GET  {base}/destination/domestic-destination?search=&limit=&offset=
 *  - POST {base}/calculate/domestic-cost (origin, destination, weight, courier)
 * Header autentikasi: `key: <RAJAONGKIR_API_KEY>`.
 */
class ShippingService
{
    /**
     * Kurir yang didukung paket FREE/Starter Komerce RajaOngkir.
     * Daftar ini divalidasi langsung oleh endpoint domestic-cost
     * (kode => nama tampilan). Urutan = kurir populer lebih dulu.
     */
    public const COURIERS = [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'anteraja' => 'AnterAja',
        'ninja' => 'Ninja Xpress',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'lion' => 'Lion Parcel',
        'ide' => 'ID Express',
        'sap' => 'SAP Express',
        'ncs' => 'NCS',
        'rex' => 'Royal Express (REX)',
        'rpx' => 'RPX',
        'sentral' => 'Sentral Cargo',
        'star' => 'Star Cargo',
        'wahana' => 'Wahana',
        'dse' => '21 Express',
    ];

    /**
     * Kurir yang sementara diaktifkan untuk ditampilkan di dropdown checkout.
     * Subset dari COURIERS — dibatasi sementara atas permintaan bisnis.
     * Kosongkan/perluas daftar ini untuk menampilkan kurir lain kembali.
     *
     * @var array<int, string>
     */
    public const ENABLED_COURIERS = [
        'jne', 'jnt', 'sicepat',
    ];

    /** Hasil pencarian wilayah jarang berubah → cache panjang (hemat kuota free). */
    private const DESTINATION_TTL = 86400; // 24 jam

    /** Tarif ongkir relatif stabil namun bisa berubah → cache menengah. */
    private const COST_TTL = 43200; // 12 jam

    private string $baseUrl;

    private ?string $apiKey;

    private ?string $origin;

    private bool $mock;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.rajaongkir.base_url'), '/');
        $this->apiKey = config('services.rajaongkir.key');
        $origin = setting('origin_district_id');
        $this->origin = blank($origin) ? null : (string) $origin;
        $this->mock = (bool) config('services.rajaongkir.mock') || blank($this->apiKey);
    }

    /**
     * Cari destinasi pengiriman (tingkat kelurahan/subdistrict) berdasarkan kata kunci.
     *
     * @return array<int, array{id: int, label: string, city: string, district: string, subdistrict: string, zip: string}>
     */
    public function searchDestinations(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < 3) {
            return [];
        }

        if ($this->mock) {
            return $this->mockDestinations($keyword);
        }

        $cacheKey = 'shipping:dest:'.md5(mb_strtolower($keyword).'|'.$limit);

        return Cache::remember($cacheKey, self::DESTINATION_TTL, function () use ($keyword, $limit): array {
            $response = Http::withHeaders(['key' => $this->apiKey])
                ->get($this->baseUrl.'/destination/domestic-destination', [
                    'search' => $keyword,
                    'limit' => $limit,
                    'offset' => 0,
                ]);

            // API mengembalikan 404 saat kata kunci tidak punya kecocokan — itu hasil
            // kosong yang wajar, bukan error sistem.
            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RajaOngkir destinasi gagal', ['status' => $response->status(), 'body' => $response->body()]);

                throw new BusinessRuleException('Gagal memuat data wilayah pengiriman.');
            }

            return array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'city' => (string) ($row['city_name'] ?? ''),
                'district' => (string) ($row['district_name'] ?? ''),
                'subdistrict' => (string) ($row['subdistrict_name'] ?? ''),
                'zip' => (string) ($row['zip_code'] ?? ''),
            ], $response->json('data') ?? []);
        });
    }

    /**
     * Kalkulasi ongkir dari origin gudang ke destinasi untuk berat (gram) & kurir.
     *
     * @return array<int, array{service: string, description: string, cost: int, etd: string}>
     */
    public function cost(int $destinationId, int $weight, string $courier): array
    {
        if ($this->mock) {
            $base = 9000 + (int) ceil($weight / 1000) * 5000;

            return [
                ['service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => $base, 'etd' => '2-3 hari'],
                ['service' => 'YES', 'description' => 'Yakin Esok Sampai', 'cost' => $base * 2, 'etd' => '1 hari'],
            ];
        }

        if (blank($this->origin)) {
            throw new BusinessRuleException('Titik asal pengiriman belum dikonfigurasi.');
        }

        $cacheKey = "shipping:cost:{$this->origin}:{$destinationId}:".max(1, $weight).":{$courier}";

        return Cache::remember($cacheKey, self::COST_TTL, function () use ($destinationId, $weight, $courier): array {
            $response = Http::withHeaders(['key' => $this->apiKey])
                ->asForm()
                ->post($this->baseUrl.'/calculate/domestic-cost', [
                    'origin' => $this->origin,
                    'destination' => $destinationId,
                    'weight' => max(1, $weight),
                    'courier' => $courier,
                ]);

            // 404 = tidak ada layanan kurir untuk rute ini (hasil kosong, bukan error).
            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RajaOngkir cost gagal', ['status' => $response->status(), 'body' => $response->body()]);

                throw new BusinessRuleException('Gagal menghitung ongkos kirim. Silakan coba lagi.');
            }

            return array_map(static fn (array $c): array => [
                'service' => (string) ($c['service'] ?? ''),
                'description' => (string) ($c['description'] ?? ''),
                'cost' => (int) ($c['cost'] ?? 0),
                'etd' => (string) ($c['etd'] ?? ''),
            ], $response->json('data') ?? []);
        });
    }

    /**
     * Data destinasi dummy untuk pengembangan tanpa kuota API.
     *
     * @return array<int, array{id: int, label: string, city: string, district: string, subdistrict: string, zip: string}>
     */
    private function mockDestinations(string $keyword): array
    {
        return [
            ['id' => 17673, 'label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910', 'city' => 'JAKARTA TIMUR', 'district' => 'CAKUNG', 'subdistrict' => 'CAKUNG BARAT', 'zip' => '13910'],
            ['id' => 12526, 'label' => 'GAMBIR, GAMBIR, JAKARTA PUSAT, DKI JAKARTA, 10110', 'city' => 'JAKARTA PUSAT', 'district' => 'GAMBIR', 'subdistrict' => 'GAMBIR', 'zip' => '10110'],
            ['id' => 13234, 'label' => 'KEBAYORAN BARU, KEBAYORAN BARU, JAKARTA SELATAN, DKI JAKARTA, 12110', 'city' => 'JAKARTA SELATAN', 'district' => 'KEBAYORAN BARU', 'subdistrict' => 'KEBAYORAN BARU', 'zip' => '12110'],
        ];
    }
}
