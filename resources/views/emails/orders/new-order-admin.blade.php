{{--
    Email "pesanan baru" untuk ADMIN GUDANG (PRD §7.2 Event 1).
    Fokus operasional: identitas pemesan, alamat & kurir, item untuk disiapkan.
    Data: $order (relasi items/voucher dimuat di BrevoService).
--}}
@extends('emails.layout')

@section('eyebrow', 'Pesanan Baru Masuk')
@section('preheader', 'Pesanan baru '.$order->order_number.' dari '.$order->customer_name.' — '.rupiah($order->grand_total).'.')
@section('heading', 'Pesanan Baru Masuk')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Pesanan baru <strong style="color:{{ $ink }};">{{ $order->order_number }}</strong> telah dibuat dan
        <strong style="color:{{ $ink }};">menunggu pembayaran</strong>. Siapkan ketersediaan stok untuk item berikut.
        Pemrosesan pengiriman dilakukan setelah pembayaran terkonfirmasi lunas.
    </p>

    {{-- Data pelanggan + tujuan pengiriman --}}
    @include('emails.orders.partials.shipping', ['heading' => 'Pemesan & Tujuan Pengiriman'])

    <div style="height:16px;"></div>

    {{-- Rincian item + total --}}
    @include('emails.orders.partials.summary')

    {{-- Tombol buka di panel admin --}}
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:{{ $ink }}; border-radius:6px;">
                <a href="{{ route('admin.orders.show', $order) }}" target="_blank"
                   style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Buka di Panel Admin
                </a>
            </td>
        </tr>
    </table>
@endsection
