<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="guest">
<main class="guest__main">
    <div class="card error-page">
        <p class="error-page__code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <p><a href="{{ url('/') }}" class="btn btn--primary">Kembali ke halaman utama</a></p>
    </div>
</main>
</body>
</html>
