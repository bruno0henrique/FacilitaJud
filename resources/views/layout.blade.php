<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="neon-auth-url" content="{{ config('facilitajud.neon_url') }}">
    <meta name="neon-session-active" content="{{ session()->has('identity') && session()->has('neon_cookies') ? '1' : '0' }}">
    <title>@yield('title', 'FacilitaJud') · FacilitaJud</title>
    <link rel="icon" href="{{ asset('brand/facilitajud-icon.png') }}" type="image/png">
    <link rel="preload" href="{{ asset('fonts/Satoshi-Variable.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
    @yield('body')
    <div id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
