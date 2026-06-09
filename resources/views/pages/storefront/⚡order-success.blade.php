<?php

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pesanan Berhasil')] #[Layout('layouts::storefront')] class extends Component
{
    public Order $order;

    public ?string $payment_method = null;

    /** Saat true, selector metode ditampilkan untuk mengganti metode pembayaran. */
    public bool $changingMethod = false;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'voucher', 'activePaymentAttempt');
        $this->payment_method = $this->order->activePaymentAttempt?->payment_method
            ?? MidtransPaymentAttemptService::supportedMethods()[0];
    }

    /**
     * Attempt aktif yang masih open (creating/pending) atau null. Sumber kebenaran
     * tunggal untuk UX: bukan state frontend. Di-load fresh tiap request.
     */
    #[Computed]
    public function attempt(): ?PaymentAttempt
    {
        $attempt = $this->order->activePaymentAttempt;

        return $attempt instanceof PaymentAttempt && $attempt->isOpen() ? $attempt : null;
    }

    /**
     * Label tampilan tiap metode pembayaran.
     * @return array<string, string>
     */
    public function methods(): array
    {
        return [
            'bca_va' => 'Virtual Account BCA',
            'bni_va' => 'Virtual Account BNI',
            'bri_va' => 'Virtual Account BRI',
            'permata_va' => 'Virtual Account Permata',
            // 'qris' => 'QRIS',
            'gopay' => 'QRIS',
        ];
    }

    /**
     * Metadata kartu metode pembayaran untuk grid pemilihan (logo + jenis).
     * `brand` = warna korporat untuk chip logo. Urutan = bank populer dulu.
     *
     * @return array<string, array{label: string, type: string, brand: string}>
     */
    public function paymentMethods(): array
    {
        return [
            'bca_va' => ['label' => 'BCA', 'type' => 'Virtual Account', 'brand' => '#005baa'],
            'bni_va' => ['label' => 'BNI', 'type' => 'Virtual Account', 'brand' => '#ee7203'],
            'bri_va' => ['label' => 'BRI', 'type' => 'Virtual Account', 'brand' => '#00529c'],
            'permata_va' => ['label' => 'Permata', 'type' => 'Virtual Account', 'brand' => '#00854a'],
            // 'qris' => ['label' => 'QRIS', 'type' => 'Bayar via QR', 'brand' => '#2b2b2b'],
            'gopay' => ['label' => 'QRIS', 'type' => 'Bayar via QRIS', 'brand' => '#2b2b2b'],
        ];
    }

    /**
     * Bayar dengan metode terpilih. Jika berbeda dari attempt aktif, service akan
     * men-supersede + membatalkan attempt lama (yang lama otomatis tidak berlaku).
     */
    public function pay(MidtransPaymentAttemptService $service): void
    {
        $this->validate([
            'payment_method' => ['required', Rule::in(MidtransPaymentAttemptService::supportedMethods())],
        ]);

        $this->dispatchSnap($service, $this->payment_method);
        $this->changingMethod = false;
    }

    /**
     * Lanjutkan pembayaran attempt aktif (metode sama) -> buka kembali popup Snap
     * dengan token yang sama (reuse, tidak membuat transaksi baru).
     */
    public function continuePayment(MidtransPaymentAttemptService $service): void
    {
        $attempt = $this->attempt();

        if (! $attempt) {
            return;
        }

        $this->dispatchSnap($service, $attempt->payment_method);
    }

    /** Tampilkan selector untuk mengganti metode pembayaran. */
    public function startChangeMethod(): void
    {
        $this->changingMethod = true;
    }

    /** Batalkan penggantian metode, kembali ke ringkasan attempt aktif. */
    public function cancelChangeMethod(): void
    {
        $this->changingMethod = false;
    }

    /**
     * Dipanggil polling (wire:poll) & callback Snap. Sinkronkan status attempt aktif
     * dari Midtrans lalu segarkan order. Begitu order LUNAS, paksa full reload agar
     * popup Snap yang masih menutupi halaman hilang dan status sukses terlihat.
     */
    public function refreshStatus(MidtransPaymentAttemptService $service): void
    {
        $service->syncActiveAttempt($this->order);
        $this->order->refresh();

        if ($this->order->status === OrderStatus::PAID) {
            // Reload penuh: menutup overlay popup Snap sekaligus merender state "Lunas".
            $this->js('window.location.reload()');
        }
    }

    /**
     * Orkestrasi UI: buat/reuse attempt via service lalu buka popup Snap di klien.
     * Seluruh logika bisnis tetap di service (PRD §3.1).
     */
    protected function dispatchSnap(MidtransPaymentAttemptService $service, string $method): void
    {
        try {
            $attempt = Cache::lock('order-pay:'.$this->order->id, 10)
                ->block(5, fn () => $service->createOrReuseActiveAttempt($this->order, $method));
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->order->refresh();

        if (blank($attempt->snap_token)) {
            Flux::toast(variant: 'danger', text: 'Gagal memuat halaman pembayaran. Coba lagi.');

            return;
        }

        $this->dispatch('snap-pay', token: $attempt->snap_token);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    {{-- Jaga relasi tetap termuat tiap render (refresh()/$wire.$refresh membuang relasi nested). --}}
    @php $order->loadMissing('items.product', 'items.variant', 'voucher'); @endphp
    @php $isPaid = $order->status === \App\Enums\OrderStatus::PAID; @endphp

    {{-- ============================ HEADER ============================ --}}
    <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:gap-5 sm:text-left">
        <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl {{ $isPaid ? 'bg-emerald-100' : 'bg-amber-100' }}">
            <flux:icon.check-circle class="size-8 {{ $isPaid ? 'text-emerald-600' : 'text-amber-600' }}" />
        </div>
        <div class="flex-1">
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Pesanan Berhasil Dibuat</h1>
            <p class="mt-1 text-sm text-slate-500">Terima kasih, {{ $order->customer_name }}. Pesanan Anda telah kami terima.</p>
        </div>

        {{-- Kode order + tombol salin (ikon → centang, label "Tersalin" saat diklik) --}}
        <div class="shrink-0" x-data="{ copied: false, t: null }">
            <button type="button"
                    @click="
                        navigator.clipboard?.writeText(@js($order->order_number));
                        copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000);
                    "
                    class="group inline-flex items-center gap-2.5 rounded-full bg-slate-900 py-1.5 pl-4 pr-2.5 ring-1 ring-slate-900/10 transition hover:bg-slate-800"
                    :title="copied ? 'Tersalin' : 'Klik untuk menyalin'">
                <span class="font-mono text-sm font-bold tracking-wider text-amber-500">{{ $order->order_number }}</span>
                <span class="h-3.5 w-px bg-white/20"></span>
                <span class="inline-flex items-center gap-1 text-xs font-medium transition-colors"
                      :class="copied ? 'text-emerald-400' : 'text-slate-300 group-hover:text-white'">
                    <span x-show="!copied"><flux:icon.clipboard-document class="size-3.5" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-3.5" /></span>
                    <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </span>
            </button>
        </div>
    </div>

    {{-- ============================ KOLOM UTAMA + SIDEBAR ============================ --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_350px] lg:items-start">

        {{-- ============ KIRI: STATUS + PEMBAYARAN ============ --}}
        <div class="space-y-6">
            @if ($isPaid)
                {{-- Status: LUNAS --}}
                <div class="relative overflow-hidden rounded-xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50 via-white to-white p-5 sm:p-6">
                    <span class="absolute inset-y-0 left-0 w-1 bg-emerald-500"></span>
                    <div class="flex items-center gap-2">
                        <flux:icon.check-badge class="size-5 text-emerald-600" />
                        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ $order->status->label() }}</span>
                    </div>
                    <p class="mt-3 max-w-md text-sm leading-relaxed text-emerald-800">
                        Pembayaran Anda telah kami terima
                        @if ($order->paid_at)
                            pada <span class="font-medium">{{ tanggal_id($order->paid_at) }}</span>
                        @endif
                        . Produk sedang dipersiapkan untuk pengiriman.
                    </p>
                </div>
            @else
                {{-- Wadah pembayaran: polling status tiap 30 detik selama belum lunas. --}}
                {{--
                    Handler @snap-pay HARUS diawali "if" (tanpa komentar di depan): Alpine
                    mendeteksi mode-statement via regex yang mengecek ekspresi diawali `if`.
                    Komentar `//` di awal membuatnya dianggap ekspresi → "Unexpected token 'if'".

                    Logika: bila snap.js belum termuat → abaikan (hindari reload loop di koneksi
                    lambat). Bila window.snap ada tapi pay() gagal (iframe basi karena <body>
                    ditukar via SPA / restore back-forward), muat ulang penuh agar snap.js
                    re-init terhadap body segar, lalu user menekan tombol sekali lagi.
                --}}
                <div
                    class="space-y-6"
                    wire:poll.30s="refreshStatus"
                    x-data
                    @snap-pay.window="
                        if (! window.snap) { return; }
                        try {
                            window.snap.pay($event.detail.token, {
                                onSuccess: () => $wire.refreshStatus(),
                                onPending: () => $wire.refreshStatus(),
                                onClose: () => $wire.refreshStatus(),
                                onError: () => $wire.$refresh(),
                            });
                        } catch (e) {
                            window.location.reload();
                        }
                    "
                >
                    {{-- Status: MENUNGGU PEMBAYARAN (hero) --}}
                    <div class="relative overflow-hidden rounded-xl border border-amber-200/70 bg-gradient-to-br from-amber-50 via-amber-50/40 to-white p-5 sm:p-6">
                        <span class="absolute inset-y-0 left-0 w-1 bg-amber-500"></span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">{{ $order->status->label() }}</span>
                        </div>
                        <p class="mt-4 text-sm text-slate-600">Selesaikan pembayaran sebesar</p>
                        <p class="mt-0.5 font-mono text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ rupiah($order->grand_total) }}</p>
                    </div>

                    @php $attempt = $this->attempt(); @endphp

                    {{-- STATE: ADA ATTEMPT AKTIF (dan tidak sedang ganti metode) --}}
                    @if ($attempt && ! $changingMethod)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Metode Pembayaran Aktif</h2>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-0.5 text-xs font-medium text-amber-700">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span> Menunggu Pembayaran
                                </span>
                            </div>

                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Metode</span>
                                    <span class="font-medium text-slate-900">{{ $this->methods()[$attempt->payment_method] ?? strtoupper($attempt->payment_method) }}</span>
                                </div>

                                @if ($attempt->vaNumber())
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">{{ $attempt->bankLabel() ? $attempt->bankLabel().' Virtual Account' : 'Nomor VA' }}</span>
                                        <span
                                            class="cursor-pointer font-mono text-base font-bold tracking-wider text-slate-900"
                                            x-data
                                            @click="navigator.clipboard?.writeText('{{ $attempt->vaNumber() }}'); $flux?.toast?.('Nomor VA disalin')"
                                            title="Klik untuk menyalin"
                                        >{{ $attempt->vaNumber() }}</span>
                                    </div>
                                @elseif ($attempt->billKey())
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Kode Biller</span>
                                        <span class="font-mono font-bold text-slate-900">{{ $attempt->billerCode() }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Bill Key</span>
                                        <span class="font-mono font-bold text-slate-900">{{ $attempt->billKey() }}</span>
                                    </div>
                                @endif

                                @if ($attempt->expired_at)
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Berlaku sampai</span>
                                        <span class="font-medium {{ $attempt->isExpired() ? 'text-red-600' : 'text-slate-900' }}">
                                            {{ tanggal_id($attempt->expired_at) }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            @if ($attempt->isExpired())
                                <p class="mt-4 rounded-sm bg-red-50 px-3 py-2 text-xs text-red-700">
                                    Batas waktu pembayaran telah lewat. Silakan ganti metode untuk membuat tagihan baru.
                                </p>
                            @endif

                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                @unless ($attempt->isExpired())
                                    <flux:button wire:click="continuePayment" variant="primary" class="w-full">
                                        Lanjutkan Pembayaran
                                    </flux:button>
                                @endunless
                                <flux:button wire:click="startChangeMethod" variant="{{ $attempt->isExpired() ? 'primary' : 'ghost' }}" icon="arrow-path" class="w-full">
                                    Ganti Metode Pembayaran
                                </flux:button>
                            </div>
                        </div>

                    {{-- STATE: PILIH / GANTI METODE --}}
                    @else
                        <div class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">
                                    {{ $attempt ? 'Ganti Metode Pembayaran' : 'Pilih Metode Pembayaran' }}
                                </h2>
                                @if ($attempt)
                                    <button type="button" wire:click="cancelChangeMethod" class="text-xs text-slate-400 transition hover:text-slate-600">Batal</button>
                                @endif
                            </div>

                            @if ($attempt)
                                <p class="mt-1 text-xs text-amber-600">
                                    Memilih metode baru akan membatalkan tagihan {{ $this->methods()[$attempt->payment_method] ?? '' }} yang lama.
                                </p>
                            @endif

                            {{-- Grid kartu metode: logo brand + jenis, centang saat terpilih --}}
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($this->paymentMethods() as $value => $m)
                                    @php $selected = $payment_method === $value; @endphp
                                    <button type="button" wire:click="$set('payment_method', '{{ $value }}')"
                                            aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                            class="group relative flex flex-col gap-3 rounded-lg border p-3 text-left transition
                                                   {{ $selected
                                                        ? 'border-amber-500 bg-amber-50/60 ring-1 ring-amber-500'
                                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' }}">
                                        {{-- Badge centang (muncul saat terpilih) --}}
                                        <span class="absolute right-2 top-2 flex size-5 items-center justify-center rounded-full bg-amber-500 text-white shadow-sm transition-transform duration-150 {{ $selected ? 'scale-100' : 'scale-0' }}">
                                            <flux:icon.check variant="micro" class="size-3.5" />
                                        </span>

                                        {{-- Chip logo brand --}}
                                        <span class="inline-flex h-8 items-center self-start rounded-md px-2.5 text-sm font-extrabold tracking-tight text-white shadow-sm"
                                              style="background-color: {{ $m['brand'] }}">{{ $m['label'] }}</span>

                                        <span class="block text-xs font-medium text-slate-400">{{ $m['type'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <flux:button wire:click="pay" variant="primary" class="mt-5 w-full">
                                {{ $attempt ? 'Buat Tagihan Baru & Bayar' : 'Bayar Sekarang' }}
                            </flux:button>

                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- ============ KANAN: RINCIAN PESANAN (sidebar lengket) ============ --}}
        <aside class="space-y-4 lg:sticky lg:top-6">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Rincian Pesanan</h2>
                    <p class="mt-0.5 font-mono text-xs text-slate-400">{{ tanggal_id($order->created_at) }}</p>
                </div>
                <div class="divide-y divide-slate-100 px-5">
                    @foreach ($order->items as $item)
                        <div class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">{{ $item->product->name ?? 'Produk' }}</p>
                                @if ($item->variant)
                                    <p class="text-xs text-slate-500">Varian: {{ $item->variant->name }}</p>
                                @endif
                                <p class="font-mono text-xs text-slate-400">{{ rupiah($item->price) }} × {{ $item->quantity }}</p>
                            </div>
                            <span class="shrink-0 font-mono font-semibold text-slate-900">{{ rupiah($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-2 border-t border-slate-200 bg-slate-50/50 px-5 py-4 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-mono text-slate-700">{{ rupiah($order->subtotal) }}</span></div>
                    @if ($order->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><span>Diskon{{ $order->voucher ? ' ('.$order->voucher->code.')' : '' }}</span><span class="font-mono">-{{ rupiah($order->discount_amount) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-slate-500">Ongkos Kirim ({{ strtoupper($order->shipping_courier) }})</span><span class="font-mono text-slate-700">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold">
                        <span class="text-slate-900">Total</span><span class="font-mono text-amber-600">{{ rupiah($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            <flux:button :href="route('products.index')" wire:navigate variant="ghost" icon="squares-2x2" class="w-full">Lanjut Belanja</flux:button>
        </aside>
    </div>
</div>
