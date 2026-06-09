<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilemparkan saat aturan bisnis dilanggar (stok habis, voucher invalid, dll).
 * Pesan selalu dalam Bahasa Indonesia agar aman ditampilkan ke pelanggan (PRD §4).
 */
class BusinessRuleException extends RuntimeException {}
