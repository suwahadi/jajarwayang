<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lonceng notifikasi: badge jumlah belum-dibaca + dropdown ringkas 5 terbaru.
 * Generik untuk admin & pelanggan (membaca auth()->user()).
 *
 * Tanpa websocket: count disegarkan via wire:poll (view) + event
 * 'notifications-updated' dari halaman daftar setelah aksi tandai/hapus.
 */
class NotificationBell extends Component
{
    /**
     * Posisi & perataan dropdown — diset saat embed agar tidak terpotong
     * (mis. di dasar sidebar pakai position="top", di header atas "bottom").
     */
    public string $position = 'bottom';

    public string $align = 'end';

    #[Computed]
    public function unreadCount(): int
    {
        return $this->user()?->unreadNotifications()->count() ?? 0;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return collect();
        }

        return $user->notifications()->latest()->limit(5)->get();
    }

    /**
     * Klik item: tandai dibaca lalu buka detail pesanan terkait (deep-link
     * sesuai role aktif). URL dihitung saat klik agar tak basi bila role berubah.
     */
    public function open(string $id): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $notification = $user->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        $this->dispatch('notifications-updated');
        $this->refreshState();

        $orderNumber = $notification?->data['order_number'] ?? null;

        if ($orderNumber !== null) {
            $this->redirect(
                route($user->isAdmin() ? 'admin.orders.show' : 'dashboard.orders.show', $orderNumber),
                navigate: true,
            );
        }
    }

    public function markAllRead(): void
    {
        $this->user()?->unreadNotifications->markAsRead();

        $this->dispatch('notifications-updated');
        $this->refreshState();
    }

    #[On('notifications-updated')]
    public function refreshState(): void
    {
        unset($this->unreadCount, $this->recent);
    }

    /**
     * URL halaman "Lihat semua" sesuai role akun aktif.
     */
    public function indexUrl(): string
    {
        return ($this->user()?->isAdmin() ?? false)
            ? route('admin.notifications.index')
            : route('dashboard.notifications.index');
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }

    private function user(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }
}
