<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slide;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] #[Layout('layouts::storefront')] class extends Component
{
    use InteractsWithCart;
    use InteractsWithWishlist;

    #[Computed]
    public function promos()
    {
        return Product::query()->active()->with('category', 'variants', 'images')
            ->whereNotNull('promo_price')
            ->latest()->take(4)->get();
    }

    #[Computed]
    public function newest()
    {
        return Product::query()->active()->with('category', 'variants', 'images')->latest()->take(8)->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->withCount('products')->orderBy('name')->get();
    }

    #[Computed]
    public function slides()
    {
        return Slide::query()->active()->orderBy('sort_order')->orderBy('id')->get();
    }
}; ?>

<div class="space-y-14">
    {{-- HERO --}}
    @if ($this->slides->isNotEmpty())
        @include('partials.storefront.hero-slider', ['slides' => $this->slides])
    @else
        {{-- Cadangan: hero statis bila belum ada slide aktif. --}}
        <section class="relative overflow-hidden rounded-3xl bg-zinc-900">
            <div class="grid items-center gap-6 px-8 py-14 md:grid-cols-2 md:px-14">
                <div>
                    <span class="inline-flex items-center rounded-full bg-amber-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">Produk CNC Asli</span>
                    <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-white md:text-5xl">
                        Presisi. Kokoh. <span class="text-amber-400">Tanpa Downtime.</span>
                    </h1>
                    <p class="mt-4 max-w-md leading-relaxed text-zinc-300">
                        {{ setting('site_tagline', 'Produk & Peralatan CNC Presisi') }}. Semua produk dijamin 100% orisinal untuk menjaga mesin Anda tetap berproduksi.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <flux:button :href="route('products.index')" wire:navigate variant="primary" icon="squares-2x2">Jelajahi Katalog</flux:button>
                        <flux:button :href="route('wishlist.index')" wire:navigate variant="ghost" class="!text-white !border-white/30 hover:!bg-white/10">Lihat Favorit</flux:button>
                    </div>
                </div>
                <div class="hidden justify-center md:flex">
                    <flux:icon.cog-6-tooth class="size-52 text-zinc-700" />
                </div>
            </div>
        </section>
    @endif

    {{-- KATEGORI --}}
    <section>
        <div class="flex items-end justify-between">
            <h2 class="text-xl font-extrabold tracking-tight text-zinc-900">Kategori Produk</h2>
            <a href="{{ route('products.index') }}" wire:navigate class="text-sm font-semibold text-amber-600 hover:underline">Lihat semua</a>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($this->categories as $category)
                <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                   class="group flex flex-col items-center gap-2 rounded-2xl border border-zinc-200 bg-white p-6 text-center transition hover:-translate-y-1 hover:border-zinc-300 hover:shadow-md">
                    <span class="grid size-12 place-items-center rounded-xl bg-amber-50 text-amber-600 transition group-hover:bg-amber-600 group-hover:text-white">
                        <flux:icon.cube class="size-7" />
                    </span>
                    <span class="text-sm font-bold text-zinc-900">{{ $category->name }}</span>
                    <span class="font-mono text-xs text-zinc-400">{{ $category->products_count }} produk</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- PROMO --}}
    @if ($this->promos->isNotEmpty())
        <section>
            <div class="flex items-end justify-between">
                <h2 class="text-xl font-extrabold tracking-tight text-zinc-900">Sedang Promo</h2>
                <a href="{{ route('products.index', ['sort' => 'diskon']) }}" wire:navigate class="text-sm font-semibold text-amber-600 hover:underline">Lihat semua</a>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->promos as $product)
                    <x-product-card :product="$product" :wire:key="'promo-'.$product->id" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- TERBARU --}}
    <section>
        <div class="flex items-end justify-between">
            <h2 class="text-xl font-extrabold tracking-tight text-zinc-900">Produk Terbaru</h2>
            <a href="{{ route('products.index', ['sort' => 'baru']) }}" wire:navigate class="text-sm font-semibold text-amber-600 hover:underline">Lihat semua</a>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->newest as $product)
                <x-product-card :product="$product" :wire:key="'new-'.$product->id" />
            @endforeach
        </div>
    </section>
</div>
