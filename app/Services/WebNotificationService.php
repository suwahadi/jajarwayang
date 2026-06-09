<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderPaidNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Membuat notifikasi in-web (lonceng) lewat channel database bawaan Laravel,
 * selaras dengan event yang memicu email pesanan.
 *
 * Dipanggil dari OrderActivityService (titik picu tunggal). Catatan desain:
 *  - Channel database = SINKRON (bukan queue) → lonceng langsung terisi tanpa
 *    perlu `queue:work`, dan baris notifikasi ikut commit/rollback bersama
 *    transaksi pesanannya (konsisten).
 *  - Best-effort: kegagalan menulis notifikasi TIDAK boleh menggagalkan
 *    checkout/pelunasan, maka dibungkus try/catch + report().
 */
final class WebNotificationService
{
    /**
     * Pesanan baru → admin + pelanggan pemilik (bila punya akun).
     * Cermin penerima email `new_order` (pelanggan + admin).
     */
    public function orderCreated(Order $order): void
    {
        $this->send($this->adminsWithCustomer($order), new NewOrderNotification($order));
    }

    /**
     * Pesanan lunas → pelanggan pemilik saja (bila punya akun).
     * Cermin penerima email `payment_paid` (hanya pelanggan); admin tidak.
     */
    public function orderPaid(Order $order): void
    {
        $customer = $this->customer($order);

        if ($customer !== null) {
            $this->send(collect([$customer]), new OrderPaidNotification($order));
        }
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, object $notification): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Semua admin + pelanggan pemilik pesanan, unik per id (cegah dobel bila
     * pembeli kebetulan juga admin).
     *
     * @return Collection<int, User>
     */
    private function adminsWithCustomer(Order $order): Collection
    {
        $recipients = User::query()->where('role', UserRole::ADMIN->value)->get();

        $customer = $this->customer($order);

        if ($customer !== null) {
            $recipients = $recipients->push($customer)->unique('id')->values();
        }

        return $recipients;
    }

    /**
     * Akun pelanggan pemilik pesanan. Checkout bersifat guest, jadi tautannya
     * adalah kesamaan email pemesan dengan email akun (lih. Order::scopeOwnedBy).
     */
    private function customer(Order $order): ?User
    {
        if (blank($order->customer_email)) {
            return null;
        }

        return User::query()->where('email', $order->customer_email)->first();
    }
}
