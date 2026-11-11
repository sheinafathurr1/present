<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="guest">
<main class="error-page">
    <div>
        @include('partials.brand-mark', ['size' => 44])
        <p class="error-page__code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="muted">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn--primary">Kembali ke halaman utama</a>
    </div>
</main>
</body>
</html>
