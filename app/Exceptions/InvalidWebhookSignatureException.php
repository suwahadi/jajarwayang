<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilemparkan saat signature notifikasi Midtrans tidak valid (kemungkinan
 * tampering / replay dari sumber tidak tepercaya). Controller menerjemahkannya
 * menjadi HTTP 403 sesuai spesifikasi webhook Midtrans.
 */
class InvalidWebhookSignatureException extends RuntimeException {}
