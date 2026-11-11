<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title') · {{ $schoolSettings->schoolName() }}</title>
</head>
<body class="guest">
<main id="main" class="guest__main">
    @yield('content')
</main>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
