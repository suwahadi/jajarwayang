<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inti pembuatan pesanan (checkout).
 *
 * Menerapkan tiga aturan mutlak PRD:
 *  - §3.2 Pessimistic locking stok (lockForUpdate, verifikasi stok SETELAH lock).
 *  - §3.3 Idempotency (cache lock + unique idempotency_key di DB).
 *  - §3.1 Seluruh logika transaksional berada di Service, bukan komponen Livewire.
 */
class OrderService
{
    public function __construct(
        private readonly VoucherService $voucherService,
        private readonly OrderActivityService $activities,
    ) {}

    /**
     * Proses checkout dan kembalikan Order. Idempoten terhadap $idempotencyKey.
     *
     * Struktur $payload:
     *  [
     *    'customer' => ['name', 'email', 'phone'],
     *    'shipping' => ['province_id','city_id','district_id','address','courier','cost'],
     *    'items'    => [['product_id', 'variant_id'?, 'quantity'], ...],
     *    'voucher_code' => ?string,
     *  ]
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws BusinessRuleException
     */
    public function checkout(array $payload, string $idempotencyKey): Order
    {
        // Jalur cepat: pesanan dengan kunci ini sudah pernah dibuat (PRD §3.3).
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Serialisasi request ber-kunci sama untuk meredam klik ganda paralel.
        $lock = Cache::lock('checkout:'.$idempotencyKey, 15);

        return $lock->block(10, function () use ($payload, $idempotencyKey): Order {
            // Cek ulang di dalam kunci untuk menutup celah race.
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            return DB::transaction(function () use ($payload, $idempotencyKey): Order {
                return $this->persistOrder($payload, $idempotencyKey);
            });
        });
    }

    /**
     * Bagian transaksional: kunci baris, validasi stok, potong stok, klaim voucher,
     * snapshot harga, dan buat record pesanan.
     *
     * @param  array<string, mixed>  $payload
     */
    private function persistOrder(array $payload, string $idempotencyKey): Order
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $payload['items'] ?? [];

        if ($items === []) {
            throw new BusinessRuleException('Keranjang belanja kosong.');
        }

        $subtotal = 0;
        $lineRows = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new BusinessRuleException('Jumlah barang tidak valid.');
            }

            $product = Product::query()->find($productId);

            if ($product === null || ! $product->is_active) {
                throw new BusinessRuleException('Produk tidak tersedia.');
            }

            // §3.2: kunci baris unit jual (sellable) DULU, baru verifikasi stok.
            // Varian bila produk bervarian; jika tidak, produk itu sendiri.
            if ($variantId !== null) {
                $sellable = Variant::query()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->find($variantId);

                if ($sellable === null) {
                    throw new BusinessRuleException('Varian tidak tersedia.');
                }

                $label = $product->name.' - '.$sellable->name;
            } else {
                if ($product->hasVariants()) {
                    throw new BusinessRuleException('Silakan pilih varian untuk '.$product->name.'.');
                }

                $sellable = Product::query()->lockForUpdate()->find($productId);
                $label = $product->name;
            }

            if ($sellable->stock < $quantity) {
                throw new BusinessRuleException('Stok barang tidak mencukupi untuk '.$label.'.');
            }

            $price = $sellable->effective_price;
            $lineTotal = $price * $quantity;
            $subtotal += $lineTotal;

            $sellable->decrement('stock', $quantity);

            $lineRows[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'price' => $price,
                'quantity' => $quantity,
                'total' => $lineTotal,
            ];
        }

        // Voucher (opsional) — klaim kuota di bawah lockForUpdate (§3.2).
        $voucher = null;
        $discount = 0;
        $voucherCode = $payload['voucher_code'] ?? null;

        if (filled($voucherCode)) {
            // Validasi cepat untuk pesan yang ramah, lalu kunci baris untuk klaim aman.
            $this->voucherService->validate((string) $voucherCode, $subtotal);

            $voucher = Voucher::query()->lockForUpdate()->where('code', $voucherCode)->first();

            if ($voucher === null || ! $voucher->hasQuotaLeft()) {
                throw new BusinessRuleException('Voucher telah melampaui batas kuota penggunaan.');
            }

            $discount = $this->voucherService->calculateDiscount($voucher, $subtotal);
            $voucher->increment('used_count');
        }

        $shipping = $payload['shipping'] ?? [];
        $shippingCost = (int) ($shipping['cost'] ?? 0);
        $grandTotal = max(0, $subtotal - $discount) + $shippingCost;

        $customer = $payload['customer'] ?? [];

        $order = Order::query()->create([
            'order_number' => $this->generateOrderNumber(),
            'idempotency_key' => $idempotencyKey,
            'customer_name' => (string) ($customer['name'] ?? ''),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => (string) ($customer['phone'] ?? ''),
            'shipping_province_id' => isset($shipping['province_id']) ? (int) $shipping['province_id'] : null,
            'shipping_city_id' => isset($shipping['city_id']) ? (int) $shipping['city_id'] : null,
            'shipping_district_id' => (int) ($shipping['destination_id'] ?? $shipping['district_id'] ?? 0),
            'shipping_destination_label' => $shipping['destination_label'] ?? null,
            'shipping_address' => (string) ($shipping['address'] ?? ''),
            'shipping_courier' => (string) ($shipping['courier'] ?? ''),
            'shipping_cost' => $shippingCost,
            'voucher_id' => $voucher?->id,
            'discount_amount' => $discount,
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'status' => OrderStatus::PENDING,
        ]);

        $order->items()->createMany($lineRows);

        // Catat riwayat "dibuat" + kirim notifikasi pesanan baru (terpusat di service).
        $this->activities->created($order);

        return $order;
    }

    /**
     * Tandai pesanan lunas dan kirim notifikasi pembayaran (PRD §7.2 Event 2).
     * $actor: nama staff (manual) atau "Midtrans (otomatis)".
     */
    public function markAsPaid(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::PAID) {
            return $order;
        }

        $order->update(['status' => OrderStatus::PAID]);

        $this->activities->paid($order, $actor);

        return $order;
    }

    /**
     * Tandai pesanan telah dikirim. Hanya valid dari status lunas.
     *
     * @throws BusinessRuleException
     */
    public function markAsShipped(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::SHIPPED) {
            return $order;
        }

        if ($order->status !== OrderStatus::PAID) {
            throw new BusinessRuleException('Hanya pesanan yang sudah lunas yang dapat dikirim.');
        }

        $order->update(['status' => OrderStatus::SHIPPED]);

        $this->activities->shipped($order, $actor);

        return $order;
    }

    /**
     * Batalkan pesanan dan kembalikan stok serta kuota voucher.
     * Pesanan yang sudah dikirim tidak dapat dibatalkan.
     *
     * @throws BusinessRuleException
     */
    public function cancel(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return $order;
        }

        if ($order->status === OrderStatus::SHIPPED) {
            throw new BusinessRuleException('Pesanan yang sudah dikirim tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($order, $actor): void {
            // Kembalikan stok tiap item ke unit jualnya (varian/produk) di bawah lock (§3.2).
            foreach ($order->items()->get() as $item) {
                if ($item->variant_id !== null) {
                    $variant = Variant::query()->lockForUpdate()->find($item->variant_id);
                    $variant?->increment('stock', $item->quantity);
                } else {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    $product?->increment('stock', $item->quantity);
                }
            }

            // Kembalikan kuota voucher bila terpakai.
            if ($order->voucher_id !== null) {
                $voucher = Voucher::query()->lockForUpdate()->find($order->voucher_id);
                if ($voucher !== null && $voucher->used_count > 0) {
                    $voucher->decrement('used_count');
                }
            }

            $order->update(['status' => OrderStatus::CANCELLED]);

            $this->activities->cancelled($order, $actor);
        });

        return $order;
    }

    /**
     * Nomor pesanan format JW-YYYYMMDD-XXXXXX (unik, dijamin index DB).
     */
    private function generateOrderNumber(): string
    {
        return 'JW-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
