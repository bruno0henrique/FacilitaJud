@extends('layout')
@section('title', 'Entrar')
@section('body')
<canvas id="login-background" class="login-background" aria-hidden="true"></canvas>
<main class="login-shell" id="conteudo"><section class="surface login-panel"><a class="brand brand-login" href="{{ route('home') }}" aria-label="FacilitaJud"><span class="brand-symbol"><img src="{{ asset('brand/facilitajud-logo.png') }}" alt="" width="1672" height="941"></span><span class="brand-wordmark">Facilita<span class="brand-jud">Jud</span></span></a><h1>{{ request()->filled('convite') ? 'Seu convite para a equipe.' : 'Seu escritório, em um só lugar.' }}</h1><p class="login-subtitle">{{ request()->filled('convite') ? 'Crie sua conta com o e-mail convidado ou entre para aceitar o convite. Seus acessos serão definidos pelo administrador.' : 'Entre para organizar o que precisa da sua atenção.' }}</p>
@if(config('facilitajud.neon_url'))
<form id="login-form" method="post" action="{{ url('/auth/neon/login') }}">@csrf<label>E-mail<input name="email" type="email" required autocomplete="username"></label><label>Senha<input name="password" type="password" required minlength="8" autocomplete="current-password"></label><label id="signup-name" hidden>Seu nome<input name="name" maxlength="200" autocomplete="name"></label><p class="form-error" role="alert" hidden></p><button class="button primary" type="submit">Entrar no escritório <x-icon name="arrow-right"/></button><p id="auth-status" class="auth-status" role="status" aria-live="polite" hidden></p></form><div class="login-links"><button class="text-link" id="toggle-signup">Criar conta</button><button class="text-link" id="recover-account">Recuperar acesso</button></div><p class="metadata">Autenticação com Neon Auth</p>
@else
<div class="setup-note"><x-icon name="lock-keyhole"/><h2>Acesso aguardando configuração</h2><p>O endereço do Neon Auth ainda não foi vinculado a esta instalação.</p><p class="metadata">Configure NEON_AUTH_BASE_URL para habilitar o login seguro.</p></div>
@endif
</section><p class="metadata">FacilitaJud · Mais leveza na rotina jurídica</p></main>
@endsection
