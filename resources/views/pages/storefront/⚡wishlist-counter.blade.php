<?php

use App\Services\WishlistService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $count = 0;

    public function mount(WishlistService $wishlist): void
    {
        $this->count = $wishlist->count();
    }

    #[On('wishlist-updated')]
    public function refresh(WishlistService $wishlist): void
    {
        $this->count = $wishlist->count();
    }
}; ?>

<span class="inline-flex">
    @if ($count > 0)
        <span class="absolute right-1.5 top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-zinc-900 px-1 font-mono text-[10px] font-bold text-white">
            {{ $count }}
        </span>
    @endif
</span>
