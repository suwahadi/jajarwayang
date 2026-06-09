<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Models\Product;
use App\Models\Variant;
use App\Services\WishlistService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    use InteractsWithCart;
    use InteractsWithWishlist;

    public Product $product;

    public ?int $variantId = null;

    public int $quantity = 1;

    /** URL gambar besar yang sedang tampil (galeri). */
    public ?string $activeImage = null;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product->load('variants.image', 'category', 'images');
        $this->variantId = $product->variants->first()?->id;
        $this->activeImage = $this->resolveVariantImage($this->variantId);
    }

    /** Judul tab/<title> dinamis: "Nama Produk - {site name}" (sufiks ditambah oleh partials.head). */
    public function rendering(View $view): void
    {
        $view->title($this->product->name);
    }

    /** Saat varian diganti, gambar besar ikut ke gambar varian (atau gambar utama). */
    public function updatedVariantId(): void
    {
        $this->activeImage = $this->resolveVariantImage($this->variantId);
    }

    /** Gambar untuk varian tertentu: gambar khusus varian bila ada, jika tidak gambar utama produk. */
    private function resolveVariantImage(?int $variantId): ?string
    {
        $this->product->loadMissing('images', 'variants.image');
        $main = $this->product->thumbnailUrl();

        if ($variantId === null) {
            return $main;
        }

        return $this->product->variants->firstWhere('id', $variantId)?->image?->url ?? $main;
    }

    /** Unit jual aktif: varian terpilih bila bervarian, else produk. */
    #[Computed]
    public function current(): Product|Variant
    {
        if ($this->product->hasVariants()) {
            return $this->product->variants->firstWhere('id', $this->variantId)
                ?? $this->product->variants->first();
        }

        return $this->product;
    }

    #[Computed]
    public function price(): int
    {
        return $this->current->effective_price;
    }

    /** Harga coret (harga normal) bila sedang promo, else null. */
    #[Computed]
    public function compareAt(): ?int
    {
        $c = $this->current;

        if (! $c->isOnPromo()) {
            return null;
        }

        return $c instanceof Variant ? $c->price : $c->original_price;
    }

    #[Computed]
    public function stock(): int
    {
        return $this->current->stock;
    }

    #[Computed]
    public function sku(): string
    {
        return $this->current->sku;
    }

    #[Computed]
    public function wished(): bool
    {
        return app(WishlistService::class)->has($this->product->id);
    }

    public function addCurrent(): void
    {
        $qty = max(1, min($this->quantity, $this->stock));
        $this->addToCart($this->product->id, $this->variantId, $qty);
    }
}; ?>

<div>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs text-zinc-400">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-amber-600">Beranda</a>
        <span>/</span>
        <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" wire:navigate class="hover:text-amber-600">{{ $product->category->name }}</a>
        <span>/</span>
        <span class="text-zinc-600">{{ $product->name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        {{-- GAMBAR --}}
        @php $product->loadMissing('images'); @endphp
        <div>
            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-white">
                @if ($activeImage)
                    <img src="{{ $activeImage }}" alt="{{ $product->name }}" class="size-full object-cover" />
                @else
                    <flux:icon.cog-6-tooth class="size-32 text-zinc-200" />
                @endif
            </div>
            @if ($product->images->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($product->images as $img)
                        <button type="button" wire:click="$set('activeImage', '{{ $img->url }}')"
                                class="size-16 overflow-hidden rounded-lg border transition {{ $activeImage === $img->url ? 'border-amber-500 ring-2 ring-amber-500' : 'border-zinc-200 hover:border-zinc-300' }}">
                            <img src="{{ $img->url }}" alt="" class="size-full object-cover" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- DETAIL --}}
        <div>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs uppercase tracking-wide text-zinc-400">SKU: {{ $this->sku }}</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-zinc-900">{{ $product->name }}</h1>
                </div>
                <button type="button" wire:click="toggleWishlist({{ $product->id }})" aria-label="Favorit"
                        @class([
                            'grid size-11 shrink-0 place-items-center rounded-full border transition',
                            'border-zinc-900 bg-zinc-900 text-amber-400' => $this->wished,
                            'border-zinc-200 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900' => ! $this->wished,
                        ])>
                    <flux:icon.heart :variant="$this->wished ? 'solid' : 'outline'" class="size-5" />
                </button>
            </div>

            <div class="mt-4">
                @if ($this->price <= 0)
                    {{-- Produk "Call": harga tersedia lewat penawaran --}}
                    <span class="font-mono text-3xl font-extrabold text-amber-600">Hubungi kami</span>
                    <p class="mt-1 text-sm text-zinc-500">Harga produk ini tersedia melalui penawaran. Silakan hubungi tim kami untuk ketersediaan dan harga.</p>
                @elseif ($this->compareAt)
                    <div class="flex items-baseline gap-3">
                        <span class="font-mono text-3xl font-extrabold text-amber-600">{{ rupiah($this->price) }}</span>
                        <span class="font-mono text-lg text-zinc-400 line-through">{{ rupiah($this->compareAt) }}</span>
                    </div>
                @else
                    <span class="font-mono text-3xl font-extrabold text-zinc-900">{{ rupiah($this->price) }}</span>
                @endif
            </div>

            {{-- Callout stok (disembunyikan untuk produk "Call") --}}
            @if ($this->price <= 0)
                @php
                    $waNumber = preg_replace('/[^0-9]/', '', (string) setting('site_phone', ''));
                    $waLink = $waNumber !== ''
                        ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Halo, saya tertarik dengan produk: '.$product->name.' (SKU: '.$this->sku.')')
                        : null;
                @endphp
                <div class="mt-6">
                    @if ($waLink)
                        <flux:button variant="primary" icon="whatsapp" :href="$waLink" target="_blank">
                            Hubungi kami via WhatsApp
                        </flux:button>
                    @else
                        <p class="text-sm text-zinc-500">Silakan hubungi tim kami untuk penawaran harga produk ini.</p>
                    @endif
                </div>
            @elseif ($this->stock <= 0)
                <div class="mt-4 rounded-r-md border-l-4 border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-700">Stok varian ini sedang habis.</div>
            @elseif ($this->stock <= 5)
                <div class="mt-4 rounded-r-md border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Ketersediaan terbatas — tersisa <span class="font-mono font-bold">{{ $this->stock }}</span> unit.
                </div>
            @else
                <p class="mt-4 font-mono text-sm text-zinc-500">Stok tersedia: {{ $this->stock }} unit</p>
            @endif

            {{-- Varian --}}
            @if ($product->variants->isNotEmpty())
                <div class="mt-6">
                    <p class="text-sm font-semibold text-zinc-900">Pilih Varian</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($product->variants as $variant)
                            <label wire:key="var-{{ $variant->id }}"
                                   class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 transition {{ $variantId === $variant->id ? 'border-amber-500 bg-amber-50' : 'border-zinc-200 hover:border-zinc-300' }}">
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="variantId" value="{{ $variant->id }}" class="accent-amber-600" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-800">{{ $variant->name }}</p>
                                        <p class="font-mono text-xs {{ $variant->stock <= 0 ? 'text-rose-500' : 'text-zinc-400' }}">
                                            {{ $variant->stock <= 0 ? 'Habis' : 'Stok '.$variant->stock }}
                                        </p>
                                    </div>
                                </div>
                                <span class="font-mono text-sm font-bold {{ $variant->isOnPromo() ? 'text-amber-600' : 'text-zinc-700' }}">{{ rupiah($variant->effective_price) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Qty + tambah (hanya untuk produk berharga) --}}
            @if ($this->price > 0 && $this->stock > 0)
                <div class="mt-6 flex items-end gap-4">
                    <div class="w-28">
                        <flux:input type="number" wire:model="quantity" label="Jumlah" min="1" :max="$this->stock" />
                    </div>
                    <flux:button variant="primary" icon="shopping-cart" wire:click="addCurrent" wire:loading.attr="disabled">
                        Tambah ke Keranjang
                    </flux:button>
                </div>
            @endif

            {{-- Deskripsi --}}
            <div class="mt-8 border-t border-zinc-200 pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900">Deskripsi Produk</h2>
                <div class="rich-text mt-3 max-w-none">{!! $product->description !!}</div>
            </div>
        </div>
    </div>
</div>
