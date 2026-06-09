<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderActivityType;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\OrderActivity;

/**
 * Sumber tunggal efek samping siklus hidup pesanan: mencatat riwayat (timeline)
 * DAN memicu notifikasi email. Semua transisi status pesanan mengalir lewat sini
 * agar mudah dikelola dalam satu lapisan service.
 */
class OrderActivityService
{
    /**
     * Label tampilan metode pembayaran (selaras dengan halaman checkout/pesanan).
     *
     * @var array<string, string>
     */
    private const METHOD_LABELS = [
        'bni_va' => 'Virtual Account BNI',
        'bri_va' => 'Virtual Account BRI',
        'bca_va' => 'Virtual Account BCA',
        'permata_va' => 'Virtual Account Permata',
        'gopay' => 'GoPay',
        'qris' => 'QRIS',
    ];

    public function __construct(
        private readonly WebNotificationService $web,
    ) {}

    /**
     * Pesanan dibuat (checkout). Memicu email "pesanan baru" (PRD §7.2 Event 1).
     */
    public function created(Order $order): void
    {
        $this->log(
            $order,
            OrderActivityType::CREATED,
            $order->customer_name,
            'Pesanan dibuat oleh '.$order->customer_name.'.',
        );

        SendBrevoEmail::dispatch($order, 'new_order');

        // Notifikasi in-web (lonceng): admin + pelanggan pemilik (bila punya akun).
        $this->web->orderCreated($order);
    }

    /**
     * Pelanggan memulai pembayaran (attempt baru dibuat).
     */
    public function paymentStarted(Order $order, string $method): void
    {
        $label = self::METHOD_LABELS[$method] ?? $method;

        $this->log(
            $order,
            OrderActivityType::PAYMENT_STARTED,
            'Pelanggan',
            'Memulai pembayaran via '.$label.'.',
            ['method' => $method],
        );
    }

    /**
     * Pesanan lunas. Memicu email "pembayaran diterima" (PRD §7.2 Event 2).
     * $actor: nama staff (manual) atau "Midtrans (otomatis)".
     */
    public function paid(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAID,
            $actor ?? 'Sistem',
            'Pembayaran diterima.',
        );

        SendBrevoEmail::dispatch($order, 'payment_paid');

        // Notifikasi in-web (lonceng): pelanggan pemilik saja (bila punya akun).
        $this->web->orderPaid($order);
    }

    /**
     * Pesanan ditandai dikirim.
     */
    public function shipped(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::SHIPPED,
            $actor ?? 'Sistem',
            'Pesanan ditandai telah dikirim.',
        );

        // Hook email pengiriman (nonaktif): tambahkan template di BrevoService lalu aktifkan.
        // SendBrevoEmail::dispatch($order, 'order_shipped');
    }

    /**
     * Pesanan dibatalkan (stok & kuota voucher dikembalikan).
     */
    public function cancelled(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::CANCELLED,
            $actor ?? 'Sistem',
            'Pesanan dibatalkan; stok & kuota voucher dikembalikan.',
        );

        // Hook email pembatalan (nonaktif).
        // SendBrevoEmail::dispatch($order, 'order_cancelled');
    }

    /**
     * Pembayaran gagal / kedaluwarsa (dari webhook Midtrans).
     */
    public function paymentFailed(Order $order, string $status): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            'Midtrans (otomatis)',
            'Pembayaran gagal/kedaluwarsa ('.$status.').',
            ['transaction_status' => $status],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(Order $order, OrderActivityType $type, ?string $actor, string $description, array $meta = []): OrderActivity
    {
        return $order->activities()->create([
            'type' => $type,
            'description' => $description,
            'actor' => $actor,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
