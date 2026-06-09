@props(['alt' => null])

{{-- Logo brand utama. Sumber tunggal: storage/app/public/assets/logo_main.png (URL: /storage/assets/logo_main.png).
     Atur ukuran lewat class tinggi dari pemanggil, mis. <x-brand-logo class="h-10" />. --}}
<img
    src="{{ asset('storage/assets/logo_main.png') }}"
    alt="{{ $alt ?? setting('site_name', 'CV. Jajar Wayang') }}"
    {{ $attributes->class('w-auto object-contain') }}
/>
