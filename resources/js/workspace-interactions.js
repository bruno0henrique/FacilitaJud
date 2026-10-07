export function setupWorkspaceInteractions({ api, toast, openEditor, dateTime }) {
    const normalize = value => value.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const contacts = [...document.querySelectorAll('[data-conversation]')];
    const messageForm = document.querySelector('#message-form');
    const history = document.querySelector('#message-history');
    let selectedClient;
    const refreshConversation = () => {
        let count = 0;
        document.querySelectorAll('[data-message-client]').forEach(message => {
            message.hidden = message.dataset.messageClient !== selectedClient;
            if (!message.hidden) count++;
        });
        const empty = document.querySelector('#conversation-empty');
        if (empty) { empty.hidden = count > 0; empty.textContent = selectedClient ? 'Comece uma conversa com este cliente.' : 'Escolha um nome para começar.'; }
        if (history) history.scrollTop = history.scrollHeight;
    };
    contacts.forEach(button => button.addEventListener('click', () => {
        selectedClient = button.dataset.conversation;
        contacts.forEach(other => other.classList.toggle('selected', other === button));
        document.querySelector('#conversation-title').textContent = button.dataset.clientName;
        if (messageForm) {
            messageForm.elements.client_id.value = selectedClient;
            messageForm.elements.body.disabled = false;
            messageForm.querySelector('button').disabled = false;
            messageForm.querySelector('.form-error').hidden = true;
        }
        refreshConversation();
    }));
    document.querySelector('#conversation-search')?.addEventListener('input', event => {
        const query = normalize(event.target.value.trim());
        contacts.forEach(button => { button.hidden = !normalize(button.dataset.clientName).includes(query); });
        document.querySelector('#conversation-no-results').hidden = contacts.some(button => !button.hidden);
    });
    contacts[0]?.click();
    messageForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = messageForm.querySelector('button');
        const error = messageForm.querySelector('.form-error');
        if (button.disabled || !selectedClient) return;
        const clientId = selectedClient;
        const body = messageForm.elements.body.value.trim();
        if (!body) return;
        button.disabled = true; button.textContent = 'Enviando…'; error.hidden = true;
        try {
            const { message } = await api('/api/v1/messages', { method: 'POST', data: { client_id: Number(clientId), body } });
            const bubble = document.createElement('div'); bubble.className = 'message-bubble outgoing'; bubble.dataset.messageClient = String(message.client_id);
            const text = document.createElement('span'); text.textContent = message.body;
            const time = document.createElement('small'); time.textContent = 'Você · agora'; bubble.append(text, time); history.append(bubble);
            if (selectedClient === clientId && messageForm.elements.body.value.trim() === body) messageForm.elements.body.value = '';
            refreshConversation();
        } catch (exception) { error.textContent = exception.message; error.hidden = false; }
        finally { button.disabled = false; button.textContent = 'Enviar'; }
    });

    const integrations = document.querySelector('#calendar-integrations');
    document.querySelector('[data-open-calendar-integrations]')?.addEventListener('click', () => integrations.showModal());
    document.querySelectorAll('[data-calendar-provider]').forEach(button => button.addEventListener('click', () => {
        const feedback = document.querySelector('#calendar-integration-feedback'); feedback.hidden = false;
        feedback.textContent = `Integração com ${button.dataset.calendarProvider} em desenvolvimento.`;
    }));
    document.querySelectorAll('[data-calendar-create]').forEach(button => button.addEventListener('click', () => {
        openEditor('appointment'); document.querySelector('#editor-form').elements.starts_at.value = `${button.dataset.calendarCreate}T09:00`;
    }));

    const inviteUrl = sessionStorage.getItem('facilitajud-invite-url');
    if (inviteUrl && document.querySelector('#invite-url')) {
        sessionStorage.removeItem('facilitajud-invite-url'); const input = document.querySelector('#invite-url'); input.value = inviteUrl; input.hidden = false; document.querySelector('#copy-invite').hidden = false;
    }
    document.querySelectorAll('[data-member-state]').forEach(button => button.addEventListener('click', async () => {
        const active = button.dataset.memberActive === '1';
        if (!active && !confirm('Remover o associado da equipe? O acesso será revogado e o histórico será preservado.')) return;
        button.disabled = true;
        try {
            const form = button.closest('form');
            const permissions = form.elements.custom_permissions.checked ? [...form.querySelectorAll('[name="permissions[]"]:checked')].map(input => input.value) : null;
            await api(`/api/v1/team/members/${button.dataset.memberState}`, { method: 'PATCH', data: { active, category_id: form.elements.category_id.value || null, responsibilities: form.elements.responsibilities.value || null, permissions } });
            sessionStorage.setItem('facilitajud-notice', active ? 'Associado restaurado.' : 'Associado removido da equipe.'); location.reload();
        } catch (exception) { toast(exception.message, true); button.disabled = false; }
    }));

    const activityDialog = document.querySelector('#activity-dialog');
    let nextActivityPage;
    const more = document.querySelector('#activity-more');
    const loadActivities = async (url, reset = false) => {
        const container = document.querySelector('#activity-history'); more.disabled = true;
        try {
            const result = await api(url);
            if (reset) container.replaceChildren();
            result.data.forEach(activity => {
                const row = document.createElement('div'); row.className = 'history-row';
                const text = document.createElement('p'); text.textContent = activity.description;
                const meta = document.createElement('small'); meta.textContent = `${dateTime(activity.created_at)} · ${activity.actor}`;
                row.append(text, meta); container.append(row);
            });
            if (!result.data.length && reset) container.textContent = 'Nenhuma atividade registrada.';
            nextActivityPage = result.next_page_url; more.hidden = !nextActivityPage;
        } catch (exception) { toast(exception.message, true); }
        finally { more.disabled = false; }
    };
    document.querySelector('#open-activity-history')?.addEventListener('click', () => { activityDialog.showModal(); loadActivities('/api/v1/activities', true); });
    more?.addEventListener('click', () => { if (nextActivityPage) loadActivities(nextActivityPage); });

    const neo = document.querySelector('#neo-chat');
    const launcher = document.querySelector('#neo-launcher');
    const neoInput = document.querySelector('#neo-input');
    const neoHistory = document.querySelector('#neo-history');
    const toggleNeo = open => { neo.hidden = !open; launcher.setAttribute('aria-expanded', String(open)); if (open) neoInput.focus(); };
    launcher?.addEventListener('click', () => toggleNeo(neo.hidden));
    document.querySelector('#neo-close')?.addEventListener('click', () => toggleNeo(false));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && neo && !neo.hidden) toggleNeo(false); });
    const reply = (text, user = false) => {
        const paragraph = document.createElement('p'); paragraph.textContent = text; paragraph.className = user ? 'neo-user-message' : 'neo-reply'; neoHistory.append(paragraph); neoHistory.scrollTop = neoHistory.scrollHeight;
        while (neoHistory.children.length > 30) neoHistory.firstElementChild.remove();
        return paragraph;
    };
    document.querySelector('#neo-voice')?.addEventListener('click', () => reply('Conversa por voz em desenvolvimento. O microfone ainda não está conectado.'));
}
