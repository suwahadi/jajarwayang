<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Keranjang belanja berbasis session untuk checkout tamu (guest).
 *
 * Hanya menyimpan referensi ringan (product_id, variant_id, quantity); harga
 * & ketersediaan selalu dihidrasi ulang dari database agar tidak basi. Logika
 * transaksional tetap di OrderService (PRD §3.1) — service ini sebatas state UI.
 */
class CartService
{
    private const KEY = 'cart';

    /**
     * Tambah item ke keranjang. Menggabungkan kuantitas bila kombinasi
     * produk+varian sudah ada. Memvalidasi keaktifan & stok produk.
     *
     * @throws BusinessRuleException
     */
    public function add(int $productId, int $quantity = 1, ?int $variantId = null): void
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('Jumlah barang minimal 1.');
        }

        $product = Product::query()->with('variants')->find($productId);

        if ($product === null || ! $product->is_active) {
            throw new BusinessRuleException('Produk tidak tersedia.');
        }

        // Resolusi unit jual (sellable): varian bila produk bervarian, else produk.
        [$variantId, $sellable, $label] = $this->resolveSellable($product, $variantId);

        $cart = $this->raw();
        $key = $this->lineKey($productId, $variantId);
        $newQty = ($cart[$key]['quantity'] ?? 0) + $quantity;

        if ($sellable->stock < $newQty) {
            throw new BusinessRuleException('Stok barang tidak mencukupi untuk '.$label.'.');
        }

        $cart[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $newQty,
        ];

        $this->save($cart);
    }

    /**
     * Tentukan unit jual & validasi pilihan varian (arsitektur hybrid).
     *
     * @return array{0: ?int, 1: Product|Variant, 2: string} [variantId ternormalisasi, sellable, label]
     *
     * @throws BusinessRuleException
     */
    private function resolveSellable(Product $product, ?int $variantId): array
    {
        if ($product->hasVariants()) {
            if ($variantId === null) {
                throw new BusinessRuleException('Silakan pilih varian terlebih dahulu.');
            }

            $variant = $product->variants->firstWhere('id', $variantId);

            if ($variant === null) {
                throw new BusinessRuleException('Varian yang dipilih tidak tersedia.');
            }

            return [$variant->id, $variant, $product->name.' - '.$variant->name];
        }

        // Produk tanpa varian: abaikan variantId, jual produk langsung.
        return [null, $product, $product->name];
    }

    /**
     * Setel kuantitas baris tertentu; quantity <= 0 menghapus baris.
     *
     * @throws BusinessRuleException
     */
    public function update(string $lineKey, int $quantity): void
    {
        $cart = $this->raw();

        if (! isset($cart[$lineKey])) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($lineKey);

            return;
        }

        $product = Product::query()->with('variants')->find($cart[$lineKey]['product_id']);
        $variantId = $cart[$lineKey]['variant_id'];
        $sellable = $variantId !== null
            ? $product?->variants->firstWhere('id', $variantId)
            : $product;

        if ($sellable === null || $sellable->stock < $quantity) {
            throw new BusinessRuleException('Stok barang tidak mencukupi.');
        }

        $cart[$lineKey]['quantity'] = $quantity;
        $this->save($cart);
    }

    public function remove(string $lineKey): void
    {
        $cart = $this->raw();
        unset($cart[$lineKey]);
        $this->save($cart);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Item keranjang terhidrasi: setiap entri berisi line key, Product, Variant
     * (opsional), harga efektif, dan subtotal baris.
     *
     * @return Collection<int, array{key: string, product: Product, variant: ?Variant, quantity: int, price: int, line_total: int, weight: int}>
     */
    public function items(): Collection
    {
        $cart = $this->raw();

        if ($cart === []) {
            return collect();
        }

        $products = Product::query()
            ->with('variants.image', 'images')
            ->whereIn('id', collect($cart)->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        return collect($cart)
            ->map(function (array $line, string $key) use ($products): ?array {
                $product = $products->get($line['product_id']);

                if ($product === null || ! $product->is_active) {
                    return null;
                }

                $variant = $line['variant_id'] !== null
                    ? $product->variants->firstWhere('id', $line['variant_id'])
                    : null;

                // Baris tak valid: varian hilang, atau produk bervarian tanpa varian terpilih.
                if (($line['variant_id'] !== null && $variant === null)
                    || ($product->hasVariants() && $variant === null)) {
                    return null;
                }

                // Sediakan relasi product agar Variant::thumbnailUrl() tak lazy-load (strict mode).
                $variant?->setRelation('product', $product);

                $sellable = $variant ?? $product;
                $price = $sellable->effective_price;

                return [
                    'key' => $key,
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $line['quantity'],
                    'price' => $price,
                    'line_total' => $price * $line['quantity'],
                    'weight' => $sellable->weight,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Jumlah total unit barang (untuk badge keranjang).
     */
    public function count(): int
    {
        return (int) collect($this->raw())->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    public function subtotal(): int
    {
        return (int) $this->items()->sum('line_total');
    }

    public function totalWeight(): int
    {
        return (int) $this->items()->sum(
            fn (array $item): int => $item['weight'] * $item['quantity'],
        );
    }

    /**
     * Bentuk payload item untuk OrderService::checkout().
     *
     * @return array<int, array{product_id: int, variant_id: ?int, quantity: int}>
     */
    public function toCheckoutItems(): array
    {
        return $this->items()
            ->map(fn (array $item): array => [
                'product_id' => $item['product']->id,
                'variant_id' => $item['variant']?->id,
                'quantity' => $item['quantity'],
            ])
            ->all();
    }

    public function lineKey(int $productId, ?int $variantId = null): string
    {
        return $productId.':'.($variantId ?? 0);
    }

    /**
     * @return array<string, array{product_id: int, variant_id: ?int, quantity: int}>
     */
    private function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    /**
     * @param  array<string, array{product_id: int, variant_id: ?int, quantity: int}>  $cart
     */
    private function save(array $cart): void
    {
        Session::put(self::KEY, $cart);
    }
}
