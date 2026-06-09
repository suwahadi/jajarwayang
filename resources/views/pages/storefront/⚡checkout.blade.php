<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] #[Layout('layouts::storefront')] class extends Component {
    // Data pelanggan
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $shipping_address = '';

    // Destinasi (RajaOngkir Komerce free — pencarian tunggal)
    public string $destinationQuery = '';
    public ?int $destinationId = null;
    public ?string $destinationLabel = null;

    // Kosong saat load awal: memaksa pelanggan memilih kurir lebih dulu.
    public string $courier = '';

    // Ongkir terpilih
    public ?string $shipping_service = null;
    public int $shipping_cost = 0;

    // Voucher
    public string $voucher_code = '';
    public ?string $applied_voucher = null;
    public int $discount = 0;

    // Idempotency (PRD §3.3)
    public string $idempotency_key = '';

    public function mount(CartService $cart): void
    {
        if ($cart->isEmpty()) {
            $this->redirectRoute('cart.index', navigate: true);

            return;
        }

        // Auto-isi data pelanggan dari akun bila user sudah login. Nomor telepon
        // diambil dari profil; bila kosong, dipakai nomor pesanan terakhirnya.
        if ($user = auth()->user()) {
            $this->customer_name = $user->name;
            $this->customer_email = $user->email;
            $this->customer_phone = $user->phone
                ?: (string) Order::ownedBy($user)->latest()->value('customer_phone');
        }

        $this->idempotency_key = (string) Str::uuid();
    }

    // ---- Pencarian destinasi ----
    #[Computed]
    public function destinationResults(): array
    {
        if ($this->destinationId !== null || mb_strlen(trim($this->destinationQuery)) < 3) {
            return [];
        }

        try {
            return app(ShippingService::class)->searchDestinations($this->destinationQuery);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return [];
        }
    }

    public function selectDestination(int $id, string $label): void
    {
        $this->destinationId = $id;
        $this->destinationLabel = $label;
        $this->destinationQuery = $label;
        $this->reset(['shipping_service', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    public function clearDestination(): void
    {
        $this->reset(['destinationId', 'destinationLabel', 'destinationQuery', 'shipping_service', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    public function updatedCourier(): void
    {
        $this->reset(['shipping_service', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    /** Kurir yang ditampilkan di dropdown (subset aktif, lihat ShippingService::ENABLED_COURIERS). */
    #[Computed]
    public function couriers(): array
    {
        return array_intersect_key(
            ShippingService::COURIERS,
            array_flip(ShippingService::ENABLED_COURIERS),
        );
    }

    #[Computed]
    public function shippingOptions(): array
    {
        if ($this->destinationId === null || $this->courier === '') {
            return [];
        }

        try {
            return app(ShippingService::class)->cost($this->destinationId, $this->weight, $this->courier);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return [];
        }
    }

    public function selectShipping(string $service, int $cost): void
    {
        $this->shipping_service = $service;
        $this->shipping_cost = $cost;
    }

    // ---- Ringkasan keranjang ----
    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(CartService::class)->subtotal();
    }

    #[Computed]
    public function weight(): int
    {
        return app(CartService::class)->totalWeight();
    }

    public function grandTotal(): int
    {
        return max(0, $this->subtotal - $this->discount) + $this->shipping_cost;
    }

    // ---- Voucher ----
    public function applyVoucher(): void
    {
        if ($this->voucher_code === '') {
            return;
        }

        try {
            $voucher = app(VoucherService::class)->validate($this->voucher_code, $this->subtotal);
            $this->discount = app(VoucherService::class)->calculateDiscount($voucher, $this->subtotal);
            $this->applied_voucher = $voucher->code;
            Flux::toast(variant: 'success', text: 'Voucher diterapkan: -'.rupiah($this->discount));
        } catch (BusinessRuleException $e) {
            $this->reset(['discount', 'applied_voucher']);
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }
    }

    public function removeVoucher(): void
    {
        $this->reset(['voucher_code', 'applied_voucher', 'discount']);
    }

    // ---- Buat pesanan ----
    public function placeOrder(CartService $cart, OrderService $orders): void
    {
        // Pesan & nama atribut Bahasa Indonesia diambil dari lang/id/validation.php
        // (termasuk pesan kustom "Silakan pilih metode pengiriman terlebih dahulu.").
        $validated = $this->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string'],
            'destinationId' => ['required', 'integer'],
            'courier' => ['required', 'string'],
            'shipping_cost' => ['required', 'integer', 'min:1'],
        ]);

        $payload = [
            'customer' => [
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'phone' => $validated['customer_phone'],
            ],
            'shipping' => [
                'destination_id' => $validated['destinationId'],
                'destination_label' => $this->destinationLabel,
                'address' => $validated['shipping_address'],
                'courier' => $validated['courier'],
                'cost' => $this->shipping_cost,
            ],
            'items' => $cart->toCheckoutItems(),
            'voucher_code' => $this->applied_voucher,
        ];

        try {
            $order = $orders->checkout($payload, $this->idempotency_key);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        // Lengkapi profil user (sekali) agar checkout berikutnya ter-prefill otomatis.
        if (($user = auth()->user()) && blank($user->phone)) {
            $user->update(['phone' => $validated['customer_phone']]);
        }

        $cart->clear();
        $this->dispatch('cart-updated');
        // Full page load (navigate: false) wajib: Midtrans snap.js (di <head>) mengikat
        // iframe pesannya ke <body> saat load. Lewat SPA wire:navigate, body ditukar tapi
        // snap.js tidak re-init -> snap.pay() postMessage ke window null. Full load = snap segar.
        $this->redirectRoute('checkout.success', ['order' => $order->order_number], navigate: false);
    }
}; ?>

<div class="pb-24 lg:pb-0">
    <h1 class="text-2xl font-bold text-slate-900">Checkout</h1>

    <form wire:submit="placeOrder" class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        {{-- ============ FORM ============ --}}
        <div class="space-y-6">
            {{-- Data pelanggan --}}
            <section class="rounded-md border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Data Pelanggan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="customer_name" label="Nama Lengkap" />
                    <flux:input wire:model="customer_phone" label="Nomor Telepon" />
                    <flux:input wire:model="customer_email" type="email" label="Email" class="sm:col-span-2" />
                </div>
            </section>

            {{-- Alamat pengiriman --}}
            <section class="rounded-md border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Alamat Pengiriman</h2>

                {{-- Pencarian destinasi --}}
                <div class="mt-4">
                    @if ($destinationId)
                        <label class="text-sm font-medium text-slate-700">Wilayah Tujuan</label>
                        <div class="mt-1 flex items-center justify-between gap-3 rounded-sm border border-emerald-300 bg-emerald-50 px-4 py-2.5">
                            <span class="text-sm font-medium text-emerald-800">{{ $destinationLabel }}</span>
                            <flux:button type="button" size="xs" variant="subtle" wire:click="clearDestination" class="cursor-pointer">Ubah</flux:button>
                        </div>
                    @else
                        <flux:input wire:model.live.debounce.500ms="destinationQuery"
                                    label="Wilayah Tujuan (Kecamatan / Kelurahan)"
                                    placeholder="Ketik min. 3 huruf, mis. Tanah Abang"
                                    icon="magnifying-glass" />

                        @if (! empty($this->destinationResults))
                            <div class="mt-2 max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-sm border border-slate-200">
                                @foreach ($this->destinationResults as $dest)
                                    <button type="button" wire:click="selectDestination({{ $dest['id'] }}, @js($dest['label']))"
                                            class="block w-full cursor-pointer px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-amber-50">
                                        {{ $dest['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($destinationQuery)) >= 3)
                            <p class="mt-2 text-xs text-slate-400" wire:loading.remove wire:target="destinationQuery">Tidak ada wilayah yang cocok.</p>
                            <p class="mt-2 text-xs text-slate-400" wire:loading wire:target="destinationQuery">Mencari wilayah...</p>
                        @endif
                    @endif

                    @error('destinationId')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4">
                    <flux:textarea wire:model="shipping_address" label="Alamat Lengkap" placeholder="Nama jalan, nomor, RT/RW, patokan..." rows="3" />
                </div>
            </section>

            {{-- Pengiriman --}}
            <section class="rounded-md border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Metode Pengiriman</h2>
                <div class="mt-4">
                    <flux:select wire:model.live="courier" label="Kurir" placeholder="Pilih kurir…"
                                 class="cursor-pointer font-medium text-slate-900 [&>option]:text-slate-900">
                        @foreach ($this->couriers as $code => $name)
                            <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('courier')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4 space-y-2">
                    @if (! $destinationId)
                        <p class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-800">Pilih wilayah tujuan untuk melihat ongkos kirim.</p>
                    @elseif ($courier === '')
                        <p class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-800">Pilih kurir terlebih dahulu untuk melihat ongkos kirim.</p>
                    @else
                        <div wire:loading.flex wire:target="shippingOptions, courier, selectDestination" class="items-center gap-2 text-sm text-slate-500">
                            <flux:icon.arrow-path class="size-4 animate-spin" /> Menghitung ongkir...
                        </div>
                        <div wire:loading.remove wire:target="shippingOptions, courier, selectDestination" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
                            @forelse ($this->shippingOptions as $opt)
                                <label class="flex cursor-pointer flex-col gap-1.5 rounded-sm border px-4 py-3 transition {{ $shipping_service === $opt['service'] ? 'border-amber-500 bg-amber-50' : 'border-slate-200 hover:border-slate-300' }}">
                                    <div class="flex items-start gap-2">
                                        <input type="radio" name="ship" class="mt-0.5 text-amber-600"
                                               @checked($shipping_service === $opt['service'])
                                               wire:click="selectShipping(@js($opt['service']), {{ $opt['cost'] }})" />
                                        <p class="text-sm font-semibold text-slate-800">{{ strtoupper($courier) }} {{ $opt['service'] }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ $opt['description'] }} &middot; Estimasi {{ $opt['etd'] }}</p>
                                    <span class="font-mono text-sm font-bold text-slate-900">{{ rupiah($opt['cost']) }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-slate-500 col-span-2 lg:col-span-3">Tidak ada layanan tersedia untuk tujuan ini.</p>
                            @endforelse
                        </div>
                    @endif

                    {{-- Pesan validasi: muncul saat tekan "Buat Pesanan" tanpa memilih metode --}}
                    @error('shipping_cost')
                        <p class="flex items-center gap-1.5 border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            <flux:icon.exclamation-triangle class="size-4 shrink-0" /> {{ $message }}
                        </p>
                    @enderror
                </div>
            </section>
        </div>

        {{-- ============ RINGKASAN ============ --}}
        <div class="h-fit space-y-4 self-start rounded-md border border-slate-200 bg-white p-5 lg:sticky lg:top-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Ringkasan Pesanan</h2>

            <div class="divide-y divide-slate-100">
                @foreach ($this->items as $item)
                    <div class="flex justify-between gap-2 py-2 text-sm">
                        <span class="text-slate-600">{{ $item['product']->name }} <span class="font-mono text-slate-400">×{{ $item['quantity'] }}</span></span>
                        <span class="shrink-0 font-mono text-slate-900">{{ rupiah($item['line_total']) }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Voucher --}}
            <div class="border-t border-slate-200 pt-4">
                @if ($applied_voucher)
                    <div class="flex items-center justify-between rounded-sm bg-emerald-50 px-3 py-2">
                        <span class="font-mono text-sm font-semibold text-emerald-700">{{ $applied_voucher }}</span>
                        <flux:button size="xs" variant="subtle" wire:click="removeVoucher" class="cursor-pointer">Hapus</flux:button>
                    </div>
                @else
                    <div class="flex items-end gap-2">
                        <flux:input wire:model="voucher_code" label="Kode Voucher" placeholder="CNCHEMAT10" size="sm" />
                        <flux:button size="sm" wire:click="applyVoucher" variant="filled" class="cursor-pointer">Pakai</flux:button>
                    </div>
                @endif
            </div>

            {{-- Total --}}
            <div class="space-y-2 border-t border-slate-200 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-mono text-slate-900">{{ rupiah($this->subtotal) }}</span></div>
                @if ($discount > 0)
                    <div class="flex justify-between text-emerald-600"><span>Diskon</span><span class="font-mono">-{{ rupiah($discount) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-slate-500">Ongkos Kirim</span><span class="font-mono text-slate-900">{{ rupiah($shipping_cost) }}</span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold">
                    <span class="text-slate-900">Total</span>
                    <span class="font-mono text-amber-600">{{ rupiah($this->grandTotal()) }}</span>
                </div>
            </div>

            {{-- Tombol desktop (di mobile diganti bar mengambang di bawah) --}}
            <div class="hidden lg:block">
                <flux:button type="submit" variant="primary" class="w-full cursor-pointer"
                             wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">Buat Pesanan</span>
                    <span wire:loading wire:target="placeOrder">Memproses...</span>
                </flux:button>
                <p class="mt-3 text-center text-xs text-slate-400">Dengan menekan tombol, Anda menyetujui ketentuan pemesanan.</p>
            </div>
        </div>

        {{-- ============ BAR MENGAMBANG (MOBILE) ============ --}}
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-4 py-3 shadow-[0_-2px_12px_rgba(0,0,0,0.08)] lg:hidden">
            <div class="flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-xs text-slate-500">Total</p>
                    <p class="font-mono text-lg font-bold text-amber-600">{{ rupiah($this->grandTotal()) }}</p>
                </div>
                <flux:button type="submit" variant="primary" class="flex-1 cursor-pointer"
                             wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">Buat Pesanan</span>
                    <span wire:loading wire:target="placeOrder">Memproses...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
