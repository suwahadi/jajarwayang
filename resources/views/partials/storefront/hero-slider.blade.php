@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Slide> $slides */
    $count = $slides->count();
@endphp

<section
    class="relative overflow-hidden rounded-3xl bg-zinc-900 shadow-sm"
    aria-label="Galeri sorotan"
    role="region"
    x-data="{
        active: 0,
        count: {{ $count }},
        timer: null,
        touch: 0,
        reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        start() {
            if (this.reduced || this.count < 2) return;
            this.stop();
            this.timer = setInterval(() => this.next(), 3000);
        },
        stop() {
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },
        next() { this.active = (this.active + 1) % this.count; },
        prev() { this.active = (this.active - 1 + this.count) % this.count; },
        go(i) { this.active = i; this.start(); },
    }"
    x-init="start()"
    @mouseenter="stop()"
    @mouseleave="start()"
    @keydown.window.arrow-left="prev(); start()"
    @keydown.window.arrow-right="next(); start()"
    @touchstart="touch = $event.changedTouches[0].clientX"
    @touchend="
        let dx = $event.changedTouches[0].clientX - touch;
        if (Math.abs(dx) > 50) { dx < 0 ? next() : prev(); start(); }
    "
>
    <div class="relative h-[360px] sm:h-[420px] md:h-[480px]">
        @foreach ($slides as $i => $slide)
            <article
                x-show="active === {{ $i }}"
                x-transition:enter="transition ease-out duration-700"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-500 absolute inset-0"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0"
                @if ($i !== 0) x-cloak @endif
                wire:key="slide-{{ $slide->id }}"
            >
                <img
                    src="{{ $slide->image_url }}"
                    alt="{{ $slide->title }}"
                    class="absolute inset-0 size-full object-cover"
                    @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                />
                {{-- Overlay agar teks tetap terbaca di atas gambar apa pun. --}}
                <div class="absolute inset-0 bg-gradient-to-r from-zinc-900/90 via-zinc-900/60 to-zinc-900/10"></div>

                <div class="relative flex h-full items-center">
                    <div class="max-w-xl px-8 py-10 md:px-14">
                        <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-white md:text-5xl">
                            {{ $slide->title }}
                        </h1>
                        @if (filled($slide->content))
                            <p class="mt-4 max-w-md text-sm leading-relaxed text-zinc-200 md:text-base">
                                {{ $slide->content }}
                            </p>
                        @endif
                        @if (filled($slide->button_label) && filled($slide->url))
                            <div class="mt-6">
                                <a
                                    href="{{ $slide->url }}"
                                    @if (\Illuminate\Support\Str::startsWith($slide->url, '/')) wire:navigate @else target="_blank" rel="noopener" @endif
                                    class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-amber-700"
                                >
                                    {{ $slide->button_label }}
                                    <flux:icon.arrow-right class="size-4" />
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach

        @if ($count > 1)
            {{-- Navigasi sebelumnya / berikutnya --}}
            <button
                type="button"
                @click="prev(); start()"
                aria-label="Sebelumnya"
                class="absolute left-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/30 md:left-5"
            >
                <flux:icon.chevron-left class="size-5" />
            </button>
            <button
                type="button"
                @click="next(); start()"
                aria-label="Berikutnya"
                class="absolute right-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/30 md:right-5"
            >
                <flux:icon.chevron-right class="size-5" />
            </button>

            {{-- Paginasi: aktif = pil lonjong, lainnya = bulat --}}
            <div class="absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2">
                @foreach ($slides as $i => $slide)
                    <button
                        type="button"
                        @click="go({{ $i }})"
                        :class="active === {{ $i }} ? 'w-7 bg-amber-500' : 'w-2.5 bg-white/50 hover:bg-white/80'"
                        class="h-2.5 rounded-full transition-all duration-300"
                        aria-label="Ke slide {{ $i + 1 }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
</section>
