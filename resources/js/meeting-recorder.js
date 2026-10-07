export class AudioUploadQueue {
    constructor(upload, onSaved = () => {}) { this.upload = upload; this.onSaved = onSaved; this.pending = []; this.nextSequence = 0; this.sentCount = 0; this.savedBytes = 0; this.running = null; }
    append(blob) {
        for (let offset = 0; offset < blob.size; offset += 262144) this.pending.push({ sequence: this.nextSequence++, blob: blob.slice(offset, offset + 262144, blob.type) });
        return this.flush();
    }
    flush() {
        if (this.running) return this.running;
        this.running = this.drain().finally(() => { this.running = null; });
        return this.running;
    }
    async drain() {
        while (this.pending.length) {
            const chunk = this.pending[0]; await this.upload(chunk);
            this.pending.shift(); this.sentCount++; this.savedBytes += chunk.blob.size; this.onSaved(this);
        }
    }
}

export function setupMeetingModule({ api, toast }) {
    const module = document.querySelector('#meetings-module'); if (!module) return;
    const terms = document.querySelector('#meeting-terms');
    let consented = module.dataset.consented === '1';
    if (!consented) terms.showModal();
    document.querySelector('#meeting-open-terms').addEventListener('click', () => terms.showModal());
    document.querySelector('#meeting-consent-form').addEventListener('submit', async event => {
        event.preventDefault(); const form = event.target; const button = form.querySelector('button.primary'); const error = form.querySelector('.form-error');
        button.disabled = true; error.hidden = true;
        try { await api('/api/v1/meetings/consent', { method: 'POST', data: { accepted: form.elements.accepted.checked } }); consented = true; terms.close(); toast('Ciência registrada. Confirme os participantes antes de cada gravação.'); }
        catch (exception) { error.textContent = exception.message; error.hidden = false; }
        finally { button.disabled = false; }
    });
    document.querySelectorAll('[data-record-meeting]').forEach(button => button.addEventListener('click', () => {
        if (!consented) { terms.showModal(); return; }
        const popup = window.open(button.dataset.recordUrl, `facilitajud-recording-${button.dataset.recordMeeting}`, 'popup,width=760,height=720');
        if (!popup) { toast('Permita pop-ups para abrir a janela de gravação.', true); return; }
        popup.opener = null; popup.focus();
    }));
    const bindForms = root => {
        root.querySelectorAll('.meeting-notes-form').forEach(form => {
            if (form.dataset.bound) return; form.dataset.bound = '1';
            form.addEventListener('input', () => { form.dataset.dirty = '1'; });
            form.addEventListener('submit', async event => {
                event.preventDefault(); const button = form.querySelector('button'); const error = form.querySelector('.form-error'); button.disabled = true; error.hidden = true;
                try { await api(`/api/v1/meeting-recordings/${form.dataset.recordingId}/notes`, { method: 'PATCH', data: { notes: form.elements.notes.value, minutes: form.elements.minutes.value } }); delete form.dataset.dirty; toast('Anotações e ata salvas.'); }
                catch (exception) { error.textContent = exception.message; error.hidden = false; }
                finally { button.disabled = false; }
            });
        });
        setupMeetingDevelopmentActions(root);
    };
    bindForms(module);
    const refresh = async (item, reveal = false) => {
        if (!item || item.dataset.refreshing) return;
        const container = item.querySelector('[data-meeting-recordings]');
        item.dataset.refreshing = '1';
        try {
            const result = await api(`/api/v1/meetings/${item.dataset.meetingId}/recordings`);
            item.querySelector('[data-recording-count]').textContent = `${result.count} ${result.count === 1 ? 'gravação' : 'gravações'}`;
            if (result.revision !== container.dataset.revision) {
                if (container.querySelector('[data-dirty="1"]') || [...container.querySelectorAll('audio')].some(audio => !audio.paused)) {
                    if (reveal) toast('Nova gravação registrada. Salve suas anotações e pause o áudio para atualizar a lista.');
                    return;
                }
                container.innerHTML = result.html; container.dataset.revision = result.revision; bindForms(container);
            }
            if (reveal) { item.open = true; toast('Gravação registrada nesta reunião.'); }
        } catch (exception) { if (reveal) toast('Não foi possível atualizar a lista. Reabra Reuniões para consultar o áudio.', true); }
        finally { delete item.dataset.refreshing; }
    };
    module.querySelectorAll('[data-meeting-id]').forEach(item => item.addEventListener('toggle', () => { if (item.open) refresh(item); }));
    const refreshVisible = () => { if (document.visibilityState !== 'hidden') module.querySelectorAll('[data-meeting-id][open]').forEach(item => refresh(item)); };
    window.addEventListener('focus', refreshVisible); document.addEventListener('visibilitychange', refreshVisible);
    if (window.BroadcastChannel) {
        const channel = new window.BroadcastChannel(module.dataset.syncChannel);
        channel.addEventListener('message', event => {
            if (event.data?.type !== 'recording-saved' || !/^\d+$/.test(String(event.data.meetingId))) return;
            refresh(module.querySelector(`[data-meeting-id="${event.data.meetingId}"]`), true);
        });
        window.addEventListener('pagehide', () => channel.close());
    }

}

export function setupMeetingRecorder({ api, toast }) {
    const windowPanel = document.querySelector('#meeting-recording-window'); if (!windowPanel) return;
    const startForm = document.querySelector('#meeting-start-form');
    const panel = document.querySelector('#meeting-recorder');
    const state = document.querySelector('#meeting-recording-state');
    const notice = document.querySelector('#meeting-live-notice');
    const error = document.querySelector('#meeting-recording-error');
    const retry = document.querySelector('#meeting-retry');
    const stop = document.querySelector('#meeting-stop');
    const timer = document.querySelector('#meeting-timer');
    const status = document.querySelector('#meeting-upload-status');
    const appointmentId = windowPanel.dataset.meetingId;
    const appointmentTitle = windowPanel.dataset.meetingTitle;
    let recorder, stream, queue, recordingId, startedAt, elapsed = 0, interval, unsettled = false, finalizing = false;
    setupMeetingDevelopmentActions(windowPanel);
    document.querySelector('#meeting-close-window').addEventListener('click', () => window.close());
    const releaseMicrophone = () => { stream?.getTracks().forEach(track => track.stop()); clearInterval(interval); };
    const showFailure = exception => {
        error.hidden = false; error.textContent = `${exception.message || 'Não foi possível salvar o áudio.'} Mantenha esta página aberta e tente salvar novamente.`;
        state.textContent = 'Captura encerrada · salvamento pendente'; notice.textContent = 'O microfone está desligado. Os trechos já enviados estão preservados; os restantes aguardam envio nesta página.';
        document.querySelector('#meeting-recording-dot').classList.add('stopped'); retry.hidden = false; stop.disabled = true;
        if (recorder?.state === 'recording') recorder.stop(); releaseMicrophone();
    };
    const finish = async () => {
        if (finalizing) return; finalizing = true; retry.disabled = true;
        try {
            state.textContent = 'Salvando gravação…';
            await queue.flush();
            const saved = await api(`/api/v1/meeting-recordings/${recordingId}/finish`, { method: 'POST', data: { duration_seconds: elapsed, chunks: queue.sentCount } });
            if (!['ready', 'empty'].includes(saved.status)) throw new Error('Não foi possível confirmar o registro da gravação.');
            unsettled = false; error.hidden = true; retry.hidden = true; state.textContent = saved.status === 'ready' ? 'Gravação salva' : 'Nenhum áudio capturado'; notice.textContent = saved.status === 'ready' ? 'O microfone está desligado. O áudio foi registrado nesta reunião.' : 'O microfone está desligado. Não recebemos áudio; reabra a gravação para tentar novamente.';
            const player = document.querySelector('#meeting-saved-audio'); if (saved.audio_url) player.src = saved.audio_url; player.hidden = saved.status !== 'ready';
            document.querySelector('#meeting-saved-actions').hidden = saved.status !== 'ready';
            if (window.BroadcastChannel) { const channel = new window.BroadcastChannel(windowPanel.dataset.syncChannel); channel.postMessage({ type: 'recording-saved', meetingId: appointmentId }); channel.close(); }
            document.querySelector('#meeting-close-window').hidden = false;
        } catch (exception) { showFailure(exception); }
        finally { finalizing = false; retry.disabled = false; }
    };
    startForm.addEventListener('submit', async event => {
        event.preventDefault(); const button = startForm.querySelector('button.primary'); const formError = startForm.querySelector('.form-error'); button.disabled = true; formError.hidden = true; recordingId = null; button.textContent = 'Autorizando microfone…';
        try {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) throw new Error('A gravação exige HTTPS ou localhost e um navegador com suporte a áudio.');
            stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true }, video: false });
            button.textContent = 'Preparando gravação…';
            const mime = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4;codecs=mp4a.40.2', 'audio/mp4'].find(type => MediaRecorder.isTypeSupported(type));
            if (!mime) throw new Error('Este navegador não oferece um formato de áudio compatível. Use Chrome, Edge, Firefox ou Safari atualizado.');
            recorder = new MediaRecorder(stream, { mimeType: mime, audioBitsPerSecond: 24000 });
            const result = await api(`/api/v1/meetings/${appointmentId}/recordings`, { method: 'POST', data: { participants_confirmed: startForm.elements.participants_confirmed.checked, participants: startForm.elements.participants.value.split(',').map(name => name.trim()).filter(Boolean), mime: recorder.mimeType || mime } });
            recordingId = result.id;
            queue = new AudioUploadQueue(async chunk => {
                const data = new FormData(); data.append('sequence', chunk.sequence); data.append('file', chunk.blob, 'trecho.audio');
                await api(`/api/v1/meeting-recordings/${recordingId}/chunks`, { method: 'POST', data });
            }, upload => { status.textContent = `${(upload.savedBytes / 1048576).toLocaleString('pt-BR',{maximumFractionDigits:2})} MB salvos · envio em pequenos trechos`; });
            recorder.addEventListener('dataavailable', event => { if (event.data.size) queue.append(event.data).catch(showFailure); });
            recorder.addEventListener('error', () => showFailure(new Error('O navegador interrompeu a gravação.')));
            recorder.addEventListener('stop', () => { elapsed = Math.max(0,Math.round((Date.now()-startedAt)/1000)); releaseMicrophone(); notice.textContent = 'O microfone está desligado. Aguarde a confirmação de salvamento.'; document.querySelector('#meeting-recording-dot').classList.add('stopped'); finish(); });
            stream.getAudioTracks().forEach(track => track.addEventListener('ended', () => { if (recorder.state === 'recording') recorder.stop(); }));
            startedAt = Date.now(); recorder.start(15000); unsettled = true; startForm.hidden = true;
            panel.hidden = false; error.hidden = true; retry.hidden = true; stop.disabled = false; document.querySelector('#meeting-recording-title').textContent = appointmentTitle;
            document.querySelector('#meeting-participants').textContent = startForm.elements.participants.value || 'Participantes informados antes do início';
            interval = setInterval(() => { const seconds = Math.floor((Date.now()-startedAt)/1000); timer.textContent = `${String(Math.floor(seconds/3600)).padStart(2,'0')}:${String(Math.floor(seconds/60)%60).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`; },1000);
            panel.scrollIntoView({behavior:'smooth',block:'center'});
        } catch (exception) {
            releaseMicrophone();
            if (recordingId && !unsettled) {
                try { await api(`/api/v1/meeting-recordings/${recordingId}/finish`, { method: 'POST', data: { duration_seconds: 0, chunks: 0 } }); } catch { /* O registro parcial permanece disponível para revisão. */ }
            }
            formError.textContent = exception.name === 'NotAllowedError' ? 'O microfone não foi autorizado. Permita o acesso no navegador para gravar.' : exception.message; formError.hidden = false;
        } finally { button.disabled = false; button.textContent = 'Iniciar gravação'; }
    });
    stop.addEventListener('click', () => { stop.disabled = true; if (recorder?.state === 'recording') recorder.stop(); });
    retry.addEventListener('click', finish);
    window.addEventListener('beforeunload', event => { if (unsettled) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', releaseMicrophone);
}

export function setupMeetingDevelopmentActions(root) {
    root.querySelectorAll('[data-meeting-ai]').forEach(button => {
        if (button.dataset.bound) return; button.dataset.bound = '1';
        button.addEventListener('click', () => { const feedback = button.parentElement.querySelector('.meeting-ai-feedback'); feedback.hidden = false; feedback.textContent = 'Resumo e ata por IA em desenvolvimento. Nenhum áudio foi enviado. Você pode registrar as anotações manualmente.'; });
    });
}
