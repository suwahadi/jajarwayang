<?php

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $count = 0;

    public function mount(CartService $cart): void
    {
        $this->count = $cart->count();
    }

    #[On('cart-updated')]
    public function refresh(CartService $cart): void
    {
        $this->count = $cart->count();
    }
}; ?>

<span class="inline-flex">
    @if ($count > 0)
        <span class="absolute right-1.5 top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-amber-600 px-1 font-mono text-[10px] font-bold text-white">
            {{ $count }}
        </span>
    @endif
</span>
