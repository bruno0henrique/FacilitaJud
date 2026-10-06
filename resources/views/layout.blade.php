<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="neon-auth-url" content="{{ config('facilitajud.neon_url') }}">
    <title>@yield('title', 'FacilitaJud') · FacilitaJud</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
    @yield('body')
    <div id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
