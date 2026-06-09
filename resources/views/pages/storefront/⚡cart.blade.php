<?php

use App\Exceptions\BusinessRuleException;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Keranjang Belanja')] #[Layout('layouts::storefront')] class extends Component {
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

    public function increment(string $key): void
    {
        $this->changeQty($key, 1);
    }

    public function decrement(string $key): void
    {
        $this->changeQty($key, -1);
    }

    private function changeQty(string $key, int $delta): void
    {
        $cart = app(CartService::class);
        $current = $cart->items()->firstWhere('key', $key);

        if ($current === null) {
            return;
        }

        try {
            $cart->update($key, $current['quantity'] + $delta);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
    }

    public function remove(string $key): void
    {
        app(CartService::class)->remove($key);
        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', text: 'Barang dihapus dari keranjang.');
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900">Keranjang Belanja</h1>

    @if ($this->items->isEmpty())
        <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-white py-20 text-center">
            <flux:icon.shopping-cart class="size-12 text-zinc-300" />
            <p class="mt-3 text-zinc-500">Keranjang Anda masih kosong.</p>
            <flux:button :href="route('products.index')" wire:navigate variant="primary" class="mt-4" icon="squares-2x2">Mulai Belanja</flux:button>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            {{-- DAFTAR ITEM --}}
            <div class="divide-y divide-zinc-200 rounded-2xl border border-zinc-200 bg-white">
                @foreach ($this->items as $item)
                    <div class="flex items-center gap-4 p-4" wire:key="cart-{{ $item['key'] }}">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-100 bg-zinc-100">
                            @php $thumb = $item['variant']?->thumbnailUrl() ?? $item['product']->thumbnailUrl(); @endphp
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $item['product']->name }}" class="size-full object-cover" loading="lazy" />
                            @else
                                <flux:icon.cog-6-tooth class="size-8 text-zinc-300" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $item['product']->slug) }}" wire:navigate class="text-sm font-semibold text-zinc-800 hover:text-amber-600">
                                {{ $item['product']->name }}
                            </a>
                            @if ($item['variant'])
                                <p class="text-xs text-zinc-500">Varian: {{ $item['variant']->name }}</p>
                            @endif
                            <p class="mt-1 font-mono text-sm text-amber-600">{{ rupiah($item['price']) }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" variant="subtle" icon="minus" wire:click="decrement('{{ $item['key'] }}')" />
                            <span class="w-8 text-center font-mono text-sm font-semibold">{{ $item['quantity'] }}</span>
                            <flux:button size="xs" variant="subtle" icon="plus" wire:click="increment('{{ $item['key'] }}')" />
                        </div>
                        <div class="w-28 text-right font-mono text-sm font-bold text-zinc-900">{{ rupiah($item['line_total']) }}</div>
                        <flux:button size="xs" variant="subtle" icon="trash" wire:click="remove('{{ $item['key'] }}')" />
                    </div>
                @endforeach
            </div>

            {{-- RINGKASAN --}}
            <div class="h-fit rounded-2xl border border-zinc-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900">Ringkasan</h2>
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-zinc-500">Subtotal</span>
                    <span class="font-mono font-semibold text-zinc-900">{{ rupiah($this->subtotal) }}</span>
                </div>
                <p class="mt-1 text-xs text-zinc-400">Ongkos kirim dihitung saat checkout.</p>
                <flux:button :href="route('checkout')" wire:navigate variant="primary" class="mt-5 w-full" icon="arrow-right">
                    Lanjut ke Checkout
                </flux:button>
                <flux:button :href="route('products.index')" wire:navigate variant="ghost" class="mt-2 w-full">Lanjut Belanja</flux:button>
            </div>
        </div>
    @endif
</div>
