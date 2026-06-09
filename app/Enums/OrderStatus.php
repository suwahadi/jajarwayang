<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status siklus hidup pesanan.
 *
 * Nilai string disimpan langsung di kolom `orders.status` (PRD §3.4).
 */
enum OrderStatus: string
{
    case PENDING = 'menunggu_pembayaran';
    case PAID = 'lunas';
    case SHIPPED = 'dikirim';
    case CANCELLED = 'dibatalkan';

    /**
     * Label tampilan dalam Bahasa Indonesia baku untuk frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran',
            self::PAID => 'Lunas',
            self::SHIPPED => 'Dikirim',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Token warna Tailwind untuk badge status (gaya industrial PRD §8).
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PAID => 'emerald',
            self::SHIPPED => 'sky',
            self::CANCELLED => 'rose',
        };
    }
}
