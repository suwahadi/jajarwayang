<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\WishlistService;
use Flux\Flux;

/**
 * Aksi toggle favorit bersama untuk komponen Livewire storefront.
 * Tetap tipis: seluruh state berada di WishlistService (sejalan dengan InteractsWithCart).
 */
trait InteractsWithWishlist
{
    public function toggleWishlist(int $productId): void
    {
        $saved = app(WishlistService::class)->toggle($productId);

        $this->dispatch('wishlist-updated');
        Flux::toast(
            variant: $saved ? 'success' : 'warning',
            text: $saved ? 'Disimpan ke favorit.' : 'Dihapus dari favorit.',
        );
    }

    /**
     * Daftar id produk favorit untuk menandai status pada kartu.
     *
     * @return array<int, int>
     */
    public function wishlistIds(): array
    {
        return app(WishlistService::class)->ids();
    }
}
