<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#ffffff">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
{{-- Terapkan ukuran teks & mode kontras pilihan pengguna sebelum halaman tampil. --}}
<script>
    (function () {
        try {
            var d = document.documentElement;
            var size = localStorage.getItem('a11y:text-size');
            if (size) d.dataset.textSize = size;
            if (localStorage.getItem('a11y:contrast') === 'high') d.dataset.contrast = 'high';
        } catch (e) {}
    })();
</script>
<link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
