@props([
    'product',
])

{{--
    Kartu produk reusable (storefront). Dipakai di katalog, beranda, dan favorit.
    Interaksi (tambah keranjang / toggle favorit) dialirkan ke komponen Livewire
    induk lewat wire:click — induk wajib memakai trait InteractsWithCart &
    InteractsWithWishlist.
--}}
@php
    $hasVariants = $product->hasVariants();
    $onPromo = $product->isOnPromo();
    $badge = $product->badgeMeta();
    $thumb = $product->thumbnailUrl();
    $soldOut = ! $hasVariants && $product->stock <= 0;
    $wished = app(\App\Services\WishlistService::class)->has($product->id);
    $isCall = $product->fromPrice() <= 0;

    // Produk "Call": tombol mengarah langsung ke WhatsApp untuk penawaran harga.
    $waNumber = preg_replace('/[^0-9]/', '', (string) setting('site_phone', ''));
    $waNumber = $waNumber !== '' ? (str_starts_with($waNumber, '0') ? '62'.substr($waNumber, 1) : $waNumber) : '';
    $waLink = $waNumber !== ''
        ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Halo, saya tertarik dengan produk: '.$product->name.' (SKU: '.$product->sku.')')
        : null;

    $discount = 0;
    if ($onPromo && ! $hasVariants && $product->original_price > 0) {
        $discount = (int) round((1 - $product->promo_price / $product->original_price) * 100);
    }
@endphp

<article {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-lg') }}>
    {{-- MEDIA --}}
    <div class="relative aspect-[4/5] overflow-hidden bg-zinc-100">
        <a href="{{ route('products.show', $product->slug) }}" wire:navigate class="block size-full">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="{{ $product->name }}"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03] {{ $soldOut ? 'opacity-60' : '' }}"
                     loading="lazy" />
            @else
                <span class="flex size-full items-center justify-center">
                    <flux:icon.cog-6-tooth class="size-16 text-zinc-300" />
                </span>
            @endif
        </a>

        {{-- Badges (kiri atas) --}}
        <div class="pointer-events-none absolute left-2.5 top-2.5 z-10 flex flex-col gap-1.5">
            @if ($soldOut)
                <span class="inline-flex h-6 items-center rounded-md bg-zinc-500 px-2 text-[11px] font-bold uppercase tracking-wide text-white">Stok Habis</span>
            @else
                @if ($badge)
                    <span @class([
                        'inline-flex h-6 items-center rounded-md px-2 text-[11px] font-bold uppercase tracking-wide',
                        'bg-emerald-600 text-white' => $badge['tone'] === 'new',
                        'bg-amber-500 text-zinc-900' => $badge['tone'] === 'hot',
                    ])>{{ $badge['label'] }}</span>
                @endif
                @if ($discount > 0)
                    <span class="inline-flex h-6 items-center rounded-md bg-zinc-900 px-2 text-[11px] font-bold uppercase tracking-wide text-white">-{{ $discount }}%</span>
                @endif
            @endif
        </div>

        {{-- Favorit (kanan atas) --}}
        <button type="button" wire:click="toggleWishlist({{ $product->id }})" wire:loading.attr="disabled"
                aria-label="{{ $wished ? 'Hapus dari favorit' : 'Simpan ke favorit' }}"
                @class([
                    'absolute right-2.5 top-2.5 z-10 grid size-9 place-items-center rounded-full border transition',
                    'border-zinc-900 bg-zinc-900 text-amber-400' => $wished,
                    'border-zinc-200 bg-white/90 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900' => ! $wished,
                ])>
            <flux:icon.heart :variant="$wished ? 'solid' : 'outline'" class="size-[18px]" />
        </button>
    </div>

    {{-- BODY --}}
    <div class="flex flex-1 flex-col p-3.5">
        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-600">{{ $product->category?->name ?? $product->sku }}</p>
        <a href="{{ route('products.show', $product->slug) }}" wire:navigate
           class="mt-1 line-clamp-2 min-h-[2.6em] text-sm font-bold leading-snug text-zinc-900 transition hover:text-amber-600">
            {{ $product->name }}
        </a>

        {{-- Harga --}}
        <div class="mt-2 flex flex-wrap items-baseline gap-x-2">
            @if ($isCall)
                {{-- Produk "Call": harga belum dipublikasikan --}}
                <span class="font-mono text-lg font-extrabold text-amber-600">Hubungi kami</span>
            @elseif ($hasVariants)
                <span class="text-[11px] font-medium text-zinc-400">Mulai</span>
                <span class="font-mono text-lg font-extrabold {{ $onPromo ? 'text-amber-600' : 'text-zinc-900' }}">{{ rupiah($product->fromPrice()) }}</span>
            @elseif ($onPromo)
                <span class="font-mono text-lg font-extrabold text-amber-600">{{ rupiah($product->promo_price) }}</span>
                <span class="font-mono text-xs text-zinc-400 line-through">{{ rupiah($product->original_price) }}</span>
            @else
                <span class="font-mono text-lg font-extrabold text-zinc-900">{{ rupiah($product->original_price) }}</span>
            @endif
        </div>

        {{-- Aksi --}}
        <div class="mt-auto pt-3">
            @if ($isCall)
                @if ($waLink)
                    <flux:button size="sm" variant="primary" class="w-full" icon="whatsapp"
                                 :href="$waLink" target="_blank">
                        Hubungi kami
                    </flux:button>
                @else
                    <flux:button size="sm" variant="primary" class="w-full" icon="whatsapp"
                                 :href="route('products.show', $product->slug)" wire:navigate>
                        Hubungi kami
                    </flux:button>
                @endif
            @elseif ($hasVariants)
                <flux:button size="sm" variant="primary" class="w-full" icon="adjustments-horizontal"
                             :href="route('products.show', $product->slug)" wire:navigate>
                    Pilih Varian
                </flux:button>
            @elseif ($soldOut)
                <flux:button size="sm" variant="filled" class="w-full" disabled>Stok Habis</flux:button>
            @else
                <flux:button size="sm" variant="primary" class="w-full" icon="plus"
                             wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled">
                    Keranjang
                </flux:button>
            @endif
        </div>
    </div>
</article>
