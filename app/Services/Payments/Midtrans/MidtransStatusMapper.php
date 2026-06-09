<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\PaymentAttemptStatus;

/**
 * Memetakan (transaction_status, fraud_status) Midtrans ke status internal.
 *
 * Dipakai bersama oleh webhook dan reconciliation job agar logika status
 * hanya hidup di satu tempat (Transaction Status Cycle Midtrans).
 */
class MidtransStatusMapper
{
    /**
     * Transaksi dianggap lunas (settlement, atau capture dengan fraud accept).
     */
    public function isPaid(?string $transactionStatus, ?string $fraudStatus): bool
    {
        if ($transactionStatus === 'settlement') {
            return true;
        }

        if ($transactionStatus === 'capture') {
            return $fraudStatus === null || $fraudStatus === 'accept';
        }

        return false;
    }

    /**
     * Status attempt internal untuk sebuah transaction_status Midtrans.
     */
    public function attemptStatus(?string $transactionStatus, ?string $fraudStatus): PaymentAttemptStatus
    {
        if ($this->isPaid($transactionStatus, $fraudStatus)) {
            return PaymentAttemptStatus::PAID;
        }

        return match ($transactionStatus) {
            'pending' => PaymentAttemptStatus::PENDING,
            'capture' => PaymentAttemptStatus::PENDING, // capture + challenge: tunggu review
            'expire' => PaymentAttemptStatus::EXPIRED,
            'cancel' => PaymentAttemptStatus::CANCELLED,
            'deny' => PaymentAttemptStatus::DENIED,
            'failure' => PaymentAttemptStatus::FAILED,
            default => PaymentAttemptStatus::FAILED,
        };
    }

    /**
     * Apakah status ini "selesai gagal" (bukan paid, bukan pending).
     */
    public function isFailureLike(?string $transactionStatus): bool
    {
        return in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true);
    }
}
