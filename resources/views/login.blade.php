@extends('layout')
@section('title', 'Entrar')
@section('body')
<main class="login-shell" id="conteudo"><section class="surface login-panel"><a class="brand" href="{{ route('home') }}"><img src="{{ asset('favicon.svg') }}" alt="" width="44" height="44"><span><strong>facilitajud</strong><small>Sua rotina, em equilíbrio.</small></span></a><h1>Seu escritório, em um só lugar.</h1><p class="login-subtitle">Entre para organizar o que precisa da sua atenção.</p>
@if(config('facilitajud.neon_url'))
<form id="login-form"><label>E-mail<input name="email" type="email" required autocomplete="username"></label><label>Senha<input name="password" type="password" required minlength="8" autocomplete="current-password"></label><label id="signup-name" hidden>Seu nome<input name="name" maxlength="200" autocomplete="name"></label><p class="form-error" role="alert" hidden></p><button class="button primary" type="submit">Entrar no escritório <x-icon name="arrow-right"/></button></form><div class="login-links"><button class="text-link" id="toggle-signup">Criar conta</button><button class="text-link" id="recover-account">Recuperar acesso</button></div><p class="metadata">Autenticação com Neon Auth</p>
@else
<div class="setup-note"><x-icon name="lock-keyhole"/><h2>Acesso aguardando configuração</h2><p>O endereço do Neon Auth ainda não foi vinculado a esta instalação.</p><p class="metadata">Configure NEON_AUTH_BASE_URL para habilitar o login seguro.</p></div>
@endif
</section><p class="metadata">FacilitaJud · Mais leveza na rotina jurídica</p></main>
@endsection
