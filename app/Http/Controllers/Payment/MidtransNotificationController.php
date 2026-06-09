<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoint webhook Midtrans (server-to-server). Tanpa auth, tanpa CSRF.
 *
 * Kontrak HTTP:
 *  - 200 : payload diterima & diproses (atau diabaikan secara bisnis/idempoten).
 *  - 403 : signature tidak valid (kemungkinan tampering) -> ditolak.
 *  - 500 : error tak terduga -> dibiarkan bubble agar Midtrans melakukan retry.
 *          Aman karena pemrosesan idempoten (lihat MidtransWebhookService).
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransWebhookService $service): Response
    {
        try {
            $service->handle($request->all());
        } catch (InvalidWebhookSignatureException) {
            return response('Invalid signature', 403);
        }

        return response('OK', 200);
    }
}
