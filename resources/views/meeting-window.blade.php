@extends('layout')
@section('title', 'Gravar reunião')
@section('body')
<main id="conteudo" class="recording-window">
<header class="recording-window-header"><img src="{{ asset('brand/facilitajud-icon.png') }}" alt="" width="40" height="40"><strong>FacilitaJud</strong><span class="metadata">Registro de reunião</span></header>
<section id="meeting-recording-window" data-sync-channel="facilitajud-meetings-{{ request()->attributes->get('office_id') }}-{{ request()->attributes->get('member_id') }}" class="surface recording-window-surface" data-meeting-id="{{ $meeting->id }}" data-meeting-title="{{ $meeting->title }}" data-meeting-url="{{ $meetingUrl }}">
<h1>{{ $meeting->title }}</h1><p class="metadata">{{ \Carbon\Carbon::parse($meeting->starts_at)->format('d/m/Y · H:i') }} · {{ $meeting->location }}</p>
<form id="meeting-start-form"><h2>Antes de iniciar</h2><p>Esta reunião será gravada e poderá ser consultada posteriormente por pessoas autorizadas. Todos os envolvidos devem saber e concordar com a gravação.</p>
<label>Participantes<input name="participants" value="{{ $actor }}" maxlength="2400" placeholder="Separe os nomes por vírgulas" required></label>
<label class="permission-toggle"><input type="checkbox" name="participants_confirmed" required> Estou ciente da gravação e confirmei que todos os envolvidos sabem e concordam.</label>
<button class="button primary">Iniciar gravação</button><p class="form-error" role="alert" hidden></p>
</form>
<section id="meeting-recorder" class="meeting-recorder" aria-label="Gravação da reunião" hidden>
<div class="recording-heading"><span id="meeting-recording-dot" class="recording-dot" aria-hidden="true"></span><strong id="meeting-recording-state" role="status">Gravação em andamento</strong></div>
<strong id="meeting-timer" class="recording-window-timer">00:00:00</strong><p id="meeting-recording-title"></p><h2>Participantes</h2><p id="meeting-participants"></p>
<p id="meeting-live-notice">O microfone está ativo. Esta reunião está sendo gravada.</p>
<p class="metadata">Você pode navegar no FacilitaJud. Mantenha esta janela aberta até salvar o áudio.</p><p id="meeting-upload-status" class="metadata" role="status">O áudio é salvo em pequenos trechos.</p>
<div class="meeting-recorder-actions"><button class="button primary" id="meeting-stop">Encerrar e salvar</button><button class="button subtle" id="meeting-retry" hidden>Tentar salvar novamente</button></div>
<p id="meeting-recording-error" class="form-error" role="alert" hidden></p><audio id="meeting-saved-audio" controls preload="none" hidden></audio><div id="meeting-saved-actions" hidden><a class="button subtle" href="{{ $meetingUrl }}" target="_blank" rel="noopener">Ver gravação na reunião</a><section class="meeting-ai"><h2>Resumo e ata por IA <span class="badge lavender">Em desenvolvimento</span></h2><p>O áudio está registrado. A geração automática de resumo e ata está em desenvolvimento; nenhum áudio será enviado à IA por este botão.</p><button class="button subtle" data-meeting-ai>Gerar resumo e ata</button><p class="meeting-ai-feedback metadata" hidden role="status"></p></section></div><button class="button subtle" id="meeting-close-window" hidden>Fechar janela</button>
</section>
</section></main>
@endsection
