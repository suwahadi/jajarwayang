@php
    use App\Models\Category;
    use App\Models\Page;

    $navCategories = Category::query()->where('is_active', true)->orderBy('name')->get();
    $footerPages = Page::query()->where('is_active', true)->orderBy('created_at', 'asc')->get();

    // Paragraf pertama halaman "Tentang Kami" untuk ringkasan footer (pakai fallback bila kosong).
    $aboutPage = Page::query()->where('slug', 'tentang-kami')->where('is_active', true)->first();
    $aboutExcerpt = ($aboutPage && preg_match('/<p[^>]*>(.*?)<\/p>/is', (string) $aboutPage->content, $m) && trim(strip_tags($m[1])) !== '')
        ? trim($m[1])
        : '<b>CV. Jajar Wayang</b> adalah penyedia global terpercaya untuk suku cadang CNC, peralatan mesin pengolahan kayu, aksesori, serta suku cadang otomatisasi industri. Kami berkomitmen pada garansi orisinalitas dan solusi yang meminimalisir downtime mesin Anda.';

    $siteName = setting('site_name', 'CV. Jajar Wayang');
    $waNumber = preg_replace('/[^0-9]/', '', (string) setting('site_phone', ''));
    $waNumber = $waNumber !== '' ? (str_starts_with($waNumber, '0') ? '62'.substr($waNumber, 1) : $waNumber) : '';
    $waLink = $waNumber !== '' ? 'https://wa.me/'.$waNumber : '#';

    $activeCategory = request()->routeIs('products.index') ? request('category') : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased" x-data="{ menu: false }">
        {{-- ============ HEADER ============ --}}
        <header class="sticky top-0 z-50 border-b border-zinc-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
                {{-- Logo --}}
                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center">
                    <x-brand-logo class="h-10" />
                </a>

                {{-- Pencarian (desktop) --}}
                <form action="{{ route('products.index') }}" method="GET" role="search"
                      class="hidden h-11 flex-1 items-center overflow-hidden rounded-full border-2 border-zinc-900 bg-white md:flex">
                    <input type="text" name="q" value="{{ request('q') }}" aria-label="Cari produk"
                           placeholder="Cari nama atau SKU produk…"
                           class="h-full flex-1 border-0 bg-transparent px-5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                    <button type="submit" aria-label="Cari" class="grid h-full w-12 place-items-center bg-zinc-900 text-white transition hover:bg-zinc-800">
                        <flux:icon.magnifying-glass class="size-[18px]" />
                    </button>
                </form>

                {{-- Aksi --}}
                <div class="ml-auto flex items-center gap-1">
                    <a href="{{ route('wishlist.index') }}" wire:navigate aria-label="Favorit"
                       class="relative grid size-11 place-items-center rounded-xl text-zinc-700 transition hover:bg-zinc-100">
                        <flux:icon.heart variant="outline" class="size-[21px]" />
                        @livewire('pages::storefront.wishlist-counter')
                    </a>
                    <a href="{{ route('cart.index') }}" wire:navigate aria-label="Keranjang"
                       class="relative grid size-11 place-items-center rounded-xl text-zinc-700 transition hover:bg-zinc-100">
                        <flux:icon.shopping-cart variant="outline" class="size-[21px]" />
                        @livewire('pages::storefront.cart-counter')
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="ml-1 hidden items-center rounded-full bg-amber-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-amber-700 lg:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="ml-1 hidden items-center rounded-full bg-amber-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-amber-700 lg:inline-flex">Masuk / Daftar</a>
                    @endauth
                    <button type="button" @click="menu = true" aria-label="Menu"
                            class="grid size-11 place-items-center rounded-xl bg-zinc-900 text-white lg:hidden">
                        <flux:icon.bars-3 class="size-5" />
                    </button>
                </div>
            </div>

            {{-- Pencarian (mobile) --}}
            <div class="px-4 pb-3 sm:px-6 md:hidden">
                <form action="{{ route('products.index') }}" method="GET" role="search"
                      class="flex h-11 items-center overflow-hidden rounded-full border-2 border-zinc-900 bg-white">
                    <input type="text" name="q" value="{{ request('q') }}" aria-label="Cari produk"
                           placeholder="Cari nama atau SKU…"
                           class="h-full flex-1 border-0 bg-transparent px-5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                    <button type="submit" aria-label="Cari" class="grid h-full w-12 place-items-center bg-zinc-900 text-white">
                        <flux:icon.magnifying-glass class="size-[18px]" />
                    </button>
                </form>
            </div>
        </header>

        {{-- ============ SUBNAV KATEGORI ============ --}}
        <nav aria-label="Kategori" class="sticky top-16 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 py-3 sm:px-6 lg:px-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ route('products.index') }}" wire:navigate
                   @class([
                       'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                       'border-zinc-900 bg-zinc-900 text-white' => request()->routeIs('products.index') && ! $activeCategory,
                       'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-400' => ! (request()->routeIs('products.index') && ! $activeCategory),
                   ])>Semua</a>
                @foreach ($navCategories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                       @class([
                           'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                           'border-zinc-900 bg-zinc-900 text-white' => $activeCategory === $category->slug,
                           'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-400' => $activeCategory !== $category->slug,
                       ])>{{ $category->name }}</a>
                @endforeach
            </div>
        </nav>

        {{-- ============ KONTEN ============ --}}
        <main class="mx-auto min-h-[60vh] w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        {{-- Strip kepercayaan & CTA mitra hanya tampil di beranda (/) dan katalog (/products). --}}
        @if (request()->routeIs('home') || request()->routeIs('products.index'))
        {{-- ============ TRUST STRIP ============ --}}
        <section class="border-y border-zinc-200 bg-white">
            <div class="mx-auto grid max-w-7xl grid-cols-2 lg:grid-cols-4">
                @php
                    $trust = [
                        ['shield-check', 'Asli 100%', 'Bergaransi orisinalitas'],
                        ['banknotes', 'Harga Kompetitif', 'Diskon untuk pembelian volume'],
                        ['cube', 'Stok Ready', 'Produk siap kirim'],
                        ['truck', 'Kirim se-Indonesia', 'Dari Aceh sampai Papua'],
                    ];
                @endphp
                @foreach ($trust as [$icon, $title, $desc])
                    <div class="flex items-center gap-3 border-b border-r border-zinc-200 p-5 lg:border-b-0 [&:nth-child(even)]:border-r-0 lg:[&:nth-child(even)]:border-r">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600">
                            <flux:icon :icon="$icon" class="size-6" />
                        </span>
                        <div>
                            <h4 class="text-sm font-bold text-zinc-900">{{ $title }}</h4>
                            <p class="text-xs text-zinc-500">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============ CTA MITRA ============ --}}
        <section class="bg-zinc-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-zinc-900 px-8 py-12 text-white md:px-14">
                    <div class="relative max-w-xl">
                        <h2 class="mt-4 text-2xl font-extrabold leading-tight tracking-tight md:text-3xl">
                            Anda Butuh <span class="text-amber-400">Penawaran Khusus?</span>
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-300 md:text-base">
                            Hubungi tim kami untuk penawaran harga grosir, ketersediaan stok partai besar, dan pendampingan teknis dari pemesanan Anda.
                        </p>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener"
                           class="mt-6 inline-flex items-center gap-2 rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-amber-700">
                            <flux:icon.whatsapp class="size-5" /> Konsultasi via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- ============ FOOTER ============ --}}
        <footer class="border-t border-zinc-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
                <div>
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" wire:navigate>
                            <x-brand-logo class="h-12" />
                        </a>
                    </div>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-500">{!! $aboutExcerpt !!}</p>
                </div>

                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-900">Produk</h4>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($navCategories as $category)
                            <li><a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate class="text-zinc-600 hover:text-amber-600">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-900">Informasi</h4>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('products.index') }}" wire:navigate class="text-zinc-600 hover:text-amber-600">Katalog Produk</a></li>
                        <li><a href="{{ route('wishlist.index') }}" wire:navigate class="text-zinc-600 hover:text-amber-600">Favorit Saya</a></li>
                        @foreach ($footerPages as $page)
                            <li><a href="{{ route('pages.show', $page->slug) }}" wire:navigate class="text-zinc-600 hover:text-amber-600">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-900">Kontak</h4>
                    <ul class="mt-4 space-y-3 text-sm text-zinc-600">
                        <li class="flex items-center gap-2.5">
                            <flux:icon.building-office-2 class="size-4 shrink-0 text-amber-600" />
                            <span class="font-semibold text-zinc-900">{{ setting('site_name', $siteName) }}</span>
                        </li>
                        @if (setting('site_address'))
                            <li class="flex items-start gap-2.5">
                                <flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-amber-600" />
                                <span>{{ setting('site_address') }}</span>
                            </li>
                        @endif
                        @if (setting('site_email'))
                            <li class="flex items-center gap-2.5">
                                <flux:icon.envelope class="size-4 shrink-0 text-amber-600" />
                                <a href="mailto:{{ setting('site_email') }}" class="font-mono hover:text-amber-600">{{ setting('site_email') }}</a>
                            </li>
                        @endif
                        @if (setting('site_phone'))
                            <li class="flex items-center gap-2.5">
                                <span class="grid size-4 shrink-0 place-items-center text-amber-600">
                                    <flux:icon.whatsapp class="size-[13px]" />
                                </span>
                                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="font-mono hover:text-amber-600">{{ setting('site_phone') }}</a>
                            </li>
                        @endif
                        <li class="flex items-center gap-2.5">
                            <flux:icon.globe-alt class="size-4 shrink-0 text-amber-600" />
                            <a href="{{ config('app.url') }}" target="_blank" rel="noopener" class="font-mono hover:text-amber-600">{{ preg_replace('#^https?://#', '', (string) config('app.url')) }}</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-zinc-200 py-5 text-center text-xs text-zinc-500">
                Copyright &copy; {{ now()->year }} {{ $siteName }}. All rights reserved.
            </div>
        </footer>

        {{-- ============ MOBILE MENU ============ --}}
        <div x-cloak x-show="menu" class="fixed inset-0 z-[110] lg:hidden" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>
            <div class="absolute right-0 top-0 flex h-full w-80 max-w-[85vw] flex-col bg-zinc-900 p-6 text-white"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                <div class="mb-8 flex items-center justify-between">
                    <span class="text-lg font-bold">Menu</span>
                    <button type="button" @click="menu = false" aria-label="Tutup" class="grid size-10 place-items-center rounded-xl bg-white/10">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>
                <nav class="flex flex-col">
                    <a href="{{ route('home') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-4 text-xl font-bold hover:text-amber-400">Beranda</a>
                    <a href="{{ route('products.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-4 text-xl font-bold hover:text-amber-400">Katalog</a>
                    <a href="{{ route('wishlist.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-4 text-xl font-bold hover:text-amber-400">Favorit</a>
                    <a href="{{ route('cart.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-4 text-xl font-bold hover:text-amber-400">Keranjang</a>
                </nav>
                <div class="mt-auto">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate @click="menu = false" class="flex w-full items-center justify-center rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate @click="menu = false" class="flex w-full items-center justify-center rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white">Masuk / Daftar</a>
                    @endauth
                </div>
            </div>
        </div>

        {{-- ============ FLOATING WHATSAPP ============ --}}
        <a href="{{ $waLink }}" target="_blank" rel="noopener" aria-label="Chat WhatsApp"
           class="fixed bottom-5 right-5 z-[90] grid size-14 place-items-center rounded-full bg-[#25D366] text-white shadow-lg shadow-[#25D366]/40 transition hover:scale-105">
            <flux:icon.whatsapp class="size-8" />
        </a>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
