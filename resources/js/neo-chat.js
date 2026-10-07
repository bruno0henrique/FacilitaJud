export async function readNeoStream(body, onEvent) {
    const reader = body.getReader(); const decoder = new TextDecoder(); let buffer = '';
    const consume = () => {
        let boundary;
        while ((boundary = buffer.indexOf('\n\n')) !== -1) {
            const block = buffer.slice(0, boundary); buffer = buffer.slice(boundary + 2);
            const type = block.split('\n').find(line => line.startsWith('event: '))?.slice(7);
            const data = block.split('\n').filter(line => line.startsWith('data: ')).map(line => line.slice(6)).join('\n');
            if (type && data) onEvent(type, JSON.parse(data));
        }
    };
    try {
        while (true) { const { value, done } = await reader.read(); if (done) break; buffer += decoder.decode(value, { stream: true }); consume(); }
        buffer += decoder.decode(); consume();
    } finally { reader.releaseLock(); }
}

export function setupNeoChat() {
    const form = document.querySelector('#neo-form'); if (!form) return;
    const input = document.querySelector('#neo-input'); const history = document.querySelector('#neo-history');
    const status = document.querySelector('#neo-status'); const send = document.querySelector('#neo-send'); const stop = document.querySelector('#neo-stop');
    let controller;
    stop.addEventListener('click', () => controller?.abort());
    document.querySelectorAll('[data-neo-suggestion]').forEach(button => button.addEventListener('click', () => { input.value = button.dataset.neoSuggestion; input.focus(); }));
    form.addEventListener('submit', async event => {
        event.preventDefault(); const message = input.value.trim(); if (!message || controller) return;
        const user = document.createElement('p'); user.className = 'neo-user-message'; user.textContent = message; history.append(user); input.value = '';
        const answer = document.createElement('p'); answer.className = 'neo-reply'; history.append(answer);
        while (history.children.length > 30) history.firstElementChild.remove();
        controller = new AbortController(); send.disabled = true; stop.hidden = false; status.textContent = 'Preparando orientação…';
        let pending = ''; let writer = null; let link = null; let terminal = false;
        const paint = () => { answer.textContent += pending.slice(0, 3); pending = pending.slice(3); history.scrollTop = history.scrollHeight; if (!pending) { clearInterval(writer); writer = null; } };
        const append = text => { pending += text; if (!writer) writer = setInterval(paint, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : 15); };
        try {
            const response = await fetch('/api/v1/neo/chat', { method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ message }) });
            if (!response.ok || !response.body) throw new Error(response.status === 401 ? 'Entre novamente para usar o Neo.' : response.status === 429 ? 'Aguarde um pouco antes de enviar outra pergunta.' : 'Não foi possível consultar o Neo. Tente novamente.');
            await readNeoStream(response.body, (type, data) => {
                if (type === 'status') status.textContent = data.text;
                if (type === 'delta') { status.textContent = 'Neo está escrevendo…'; append(data.text); }
                if (type === 'done') { terminal = true; link = data.link; }
                if (type === 'error') { terminal = true; status.textContent = data.text; }
            });
            while (pending) await new Promise(resolve => setTimeout(resolve, 20));
            if (!terminal) throw new Error('A conexão foi interrompida. Tente novamente.');
            if (link) { const anchor = document.createElement('a'); const url = new URL(link.url, location.origin); if (url.origin === location.origin) { anchor.href = url.href; anchor.className = 'text-link'; anchor.textContent = link.label; answer.append(document.createElement('br'), anchor); } }
            if (status.textContent === 'Neo está escrevendo…') status.textContent = '';
        } catch (error) { status.textContent = error.name === 'AbortError' ? 'Resposta interrompida.' : error.message; }
        finally { clearInterval(writer); if (pending) answer.textContent += pending; controller = null; send.disabled = false; stop.hidden = true; history.scrollTop = history.scrollHeight; }
    });
}
