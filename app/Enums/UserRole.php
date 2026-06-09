<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Peran akun pengguna.
 *
 * - ADMIN    : akses penuh panel /admin.
 * - CUSTOMER : pengguna toko biasa; hanya area /dashboard (+ storefront).
 *
 * Nilai disimpan di kolom `users.role`.
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::CUSTOMER => 'Pelanggan',
        };
    }
}
