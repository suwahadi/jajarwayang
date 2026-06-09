<?php

declare(strict_types=1);

use App\Services\SettingService;
use App\Support\ImageWebp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * Ambil konfigurasi situs dinamis dari tabel `settings` (PRD §3.5).
     *
     * Penggunaan: setting('site_name', 'CV. Jajar Wayang')
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return SettingService::get($key, $default);
    }
}

if (! function_exists('order_expiry_minutes')) {
    /**
     * Masa berlaku pembayaran order dalam menit, dari setting `expiry_order`.
     * Disinkronkan dengan Snap `expiry` Midtrans & `expired_at` lokal.
     *
     * Default 5 menit; minimal 1 menit agar tidak pernah <= 0.
     */
    function order_expiry_minutes(): int
    {
        return max(1, (int) setting('expiry_order', 5));
    }
}

if (! function_exists('rupiah')) {
    /**
     * Format nominal rupiah sesuai PRD §4.2: "Rp 521.000" (tanpa desimal).
     */
    function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}

if (! function_exists('tanggal_id')) {
    /**
     * Format tanggal lokal Indonesia sesuai PRD §4.1: "4 Juni 2026 09:39:12".
     */
    function tanggal_id(mixed $date): string
    {
        return Carbon::parse($date)->locale('id')->isoFormat('D MMMM YYYY HH:mm:ss');
    }
}

if (! function_exists('store_webp')) {
    /**
     * Konversi & kompres gambar yang di-upload ke WebP (disk 'public').
     * Mengembalikan path relatif disk, mis. "products/abc123.webp".
     */
    function store_webp(UploadedFile $file, string $folder = 'products'): string
    {
        return ImageWebp::store($file, $folder);
    }
}

if (! function_exists('delete_webp')) {
    /**
     * Hapus file webp dari disk 'public'. Aman bila path null / tidak ada.
     */
    function delete_webp(?string $path): void
    {
        ImageWebp::delete($path);
    }
}
