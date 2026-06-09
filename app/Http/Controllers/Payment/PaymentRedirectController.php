<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman tujuan setelah user menutup popup Snap (finish callback).
 *
 * Midtrans menambahkan ?order_id=... (yaitu midtrans_order_id, bukan id order).
 * Kita petakan kembali ke order_number internal lalu arahkan ke halaman order.
 */
class PaymentRedirectController extends Controller
{
    public function finish(Request $request): RedirectResponse
    {
        $midtransOrderId = (string) $request->query('order_id', '');

        $attempt = PaymentAttempt::query()
            ->with('order:id,order_number')
            ->where('midtrans_order_id', $midtransOrderId)
            ->first();

        if ($attempt?->order) {
            return redirect()->route('checkout.success', ['order' => $attempt->order->order_number]);
        }

        return redirect()->route('home');
    }
}
