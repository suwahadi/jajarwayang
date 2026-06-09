<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien email transaksional Brevo v3 (PRD §7.2).
 *
 * Hanya bertugas membangun payload & mengirim; pemanggilan dilakukan dari
 * Job ber-queue (App\Jobs\SendBrevoEmail) agar respons aplikasi tetap cepat.
 */
class BrevoService
{
    /**
     * Kirim satu email transaksional ke endpoint Brevo.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): bool
    {
        $baseUrl = rtrim((string) config('services.brevo.base_url'), '/');
        $apiKey = config('services.brevo.key');

        if (blank($apiKey)) {
            Log::info('Brevo API key kosong; email dilewati.', ['subject' => $payload['subject'] ?? null]);

            return false;
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->post($baseUrl.'/smtp/email', $payload);

        if ($response->failed()) {
            Log::error('Brevo gagal mengirim email', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Payload event "new_order" untuk PELANGGAN (PRD §7.2 Event 1).
     * Konfirmasi pesanan diterima + ajakan menyelesaikan pembayaran.
     *
     * @return array<string, mixed>
     */
    public function buildNewOrderCustomerPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pesanan Anda #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.new-order-customer', $order),
        ];
    }

    /**
     * Payload event "new_order" untuk ADMIN GUDANG (PRD §7.2 Event 1).
     * Notifikasi operasional: identitas pemesan, tujuan kirim, item disiapkan.
     *
     * @return array<string, mixed>
     */
    public function buildNewOrderAdminPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => (string) config('services.brevo.admin_email'), 'name' => 'Gudang CV. Jajar Wayang'],
            ],
            'subject' => '[Pesanan Baru] #'.$order->order_number.' — '.$order->customer_name,
            'htmlContent' => $this->render('emails.orders.new-order-admin', $order),
        ];
    }

    /**
     * Payload event "payment_paid" — ke pelanggan (PRD §7.2 Event 2).
     *
     * @return array<string, mixed>
     */
    public function buildPaymentPaidPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pembayaran Diterima — Pesanan #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.payment-paid', $order),
        ];
    }

    /**
     * Render badan email dari blade template. Relasi yang dipakai template
     * dimuat di sini (Job menerima Order yang baru di-fetch tanpa relasi).
     */
    private function render(string $view, Order $order): string
    {
        $order->loadMissing('items.product', 'items.variant', 'voucher');

        return view($view, ['order' => $order])->render();
    }

    /**
     * @return array{name: string, email: string}
     */
    private function sender(): array
    {
        return [
            'name' => (string) config('services.brevo.sender_name'),
            'email' => (string) config('services.brevo.sender_email'),
        ];
    }
}
