<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis notifikasi in-web (lonceng), selaras dengan event email pesanan.
 *
 * Nilai string disimpan di payload `notifications.data->type` sehingga
 * render (ikon/warna/label) konsisten antara dropdown lonceng & halaman daftar.
 */
enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_PAID = 'order_paid';

    /**
     * Label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Pesanan Baru',
            self::ORDER_PAID => 'Pembayaran Diterima',
        };
    }

    /**
     * Nama ikon Flux (Heroicons) untuk titik notifikasi.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'bell',
            self::ORDER_PAID => 'check-circle',
        };
    }

    /**
     * Token warna Tailwind/Flux untuk ikon notifikasi (selaras OrderActivityType).
     */
    public function color(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'amber',
            self::ORDER_PAID => 'emerald',
        };
    }
}
