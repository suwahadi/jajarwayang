<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * User boleh melihat pesanan hanya bila email pemesan sama dengan
     * email akunnya. Mencegah akses lewat tebak nomor pesanan.
     */
    public function view(User $user, Order $order): bool
    {
        return $order->customer_email === $user->email;
    }
}
