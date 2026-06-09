{{--
    Kontrol filter katalog. Disertakan (@include) dua kali — sidebar desktop &
    drawer mobile — sehingga berbagi scope komponen Livewire induk. Semua
    wire:model / wire:click terikat ke komponen katalog.
--}}
<div class="space-y-6">
    {{-- Kategori (pilih satu, selaras dengan chip subnav) --}}
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">Kategori</p>
        <div class="mt-3 space-y-0.5">
            <button type="button" wire:click="$set('category', '')"
                    @class([
                        'block w-full rounded-md px-2.5 py-2 text-left text-sm transition',
                        'bg-zinc-900 font-semibold text-white' => $category === '',
                        'text-zinc-600 hover:bg-zinc-100' => $category !== '',
                    ])>Semua Kategori</button>
            @foreach ($this->categories as $cat)
                <button type="button" wire:click="$set('category', '{{ $cat->slug }}')"
                        @class([
                            'block w-full rounded-md px-2.5 py-2 text-left text-sm transition',
                            'bg-zinc-900 font-semibold text-white' => $category === $cat->slug,
                            'text-zinc-600 hover:bg-zinc-100' => $category !== $cat->slug,
                        ])>{{ $cat->name }}</button>
            @endforeach
        </div>
    </div>

    {{-- Harga --}}
    <div class="border-t border-zinc-200 pt-5">
        <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">Harga</p>
        <div class="mt-3 space-y-2">
            @foreach ([['', 'Semua harga'], ['a', 'Di bawah Rp 250rb'], ['b', 'Rp 250rb – Rp 1jt'], ['c', 'Di atas Rp 1jt']] as [$val, $label])
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-600">
                    <input type="radio" wire:model.live="priceBand" value="{{ $val }}" class="size-4 accent-amber-600" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    {{-- Status --}}
    <div class="border-t border-zinc-200 pt-5">
        <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">Status</p>
        <div class="mt-3 space-y-2">
            @foreach ([['new', 'Baru'], ['hot', 'Terlaris'], ['sale', 'Sedang Diskon']] as [$val, $label])
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-600">
                    <input type="checkbox" wire:model.live="badges" value="{{ $val }}" class="size-4 rounded accent-amber-600" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
</div>
