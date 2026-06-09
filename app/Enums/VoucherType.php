<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipe potongan voucher.
 *
 * Nilai string mengikuti PRD §3.4 (sumber kebenaran atas inkonsistensi §5.4).
 */
enum VoucherType: string
{
    case PERCENTAGE = 'persentase';
    case FIXED = 'nominal_tetap';

    /**
     * Label tampilan dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Persentase',
            self::FIXED => 'Nominal Tetap',
        };
    }
}
