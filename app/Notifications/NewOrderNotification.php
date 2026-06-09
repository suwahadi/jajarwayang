<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-web "pesanan baru" — cerminan event email `new_order`.
 *
 * Penerima: admin (perlu memproses) + pelanggan pemilik pesanan bila punya akun.
 * Teks disesuaikan per penerima lewat $notifiable.
 */
final class NewOrderNotification extends Notification
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
     * Payload disimpan ke kolom `notifications.data`. URL deep-link sengaja
     * dihitung saat render (berdasarkan role aktif), jadi tidak disimpan di sini.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'type' => NotificationType::ORDER_CREATED->value,
            'order_number' => $this->order->order_number,
            'title' => $isAdmin
                ? 'Pesanan baru '.$this->order->order_number
                : 'Pesanan berhasil dibuat',
            'message' => $isAdmin
                ? $this->order->customer_name.' membuat pesanan '.rupiah($this->order->grand_total).'.'
                : 'Pesanan '.$this->order->order_number.' menunggu pembayaran.',
        ];
    }
}
