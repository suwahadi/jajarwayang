<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-web "pembayaran diterima" — cerminan event email `payment_paid`.
 *
 * Penerima: pelanggan pemilik pesanan bila punya akun. Admin TIDAK menerima
 * notifikasi pelunasan (selaras email yang hanya dikirim ke pelanggan).
 */
final class OrderPaidNotification extends Notification
{
    public function __construct(public readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationType::ORDER_PAID->value,
            'order_number' => $this->order->order_number,
            'title' => 'Pembayaran diterima',
            'message' => 'Pembayaran pesanan '.$this->order->order_number.' berhasil. Terima kasih!',
        ];
    }
}
