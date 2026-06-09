<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status siklus hidup satu attempt pembayaran Midtrans.
 *
 * Detail status pembayaran disimpan di sini, BUKAN di OrderStatus, agar enum
 * status pesanan tetap ramping (menunggu_pembayaran -> lunas).
 */
enum PaymentAttemptStatus: string
{
    case CREATING = 'creating';
    case PENDING = 'pending';
    case PAID = 'paid';
    case DENIED = 'denied';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case SUPERSEDED = 'superseded';
    case FAILED = 'failed';

    /**
     * Status yang masih bisa "hidup" (menunggu aksi user / notifikasi Midtrans).
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::CREATING, self::PENDING];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
